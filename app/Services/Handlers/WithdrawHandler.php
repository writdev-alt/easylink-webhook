<?php

namespace App\Services\Handlers;

use App\Enums\TrxStatus;
use App\Exceptions\NotifyErrorException;
use App\Models\Transaction;
use App\Payment\Easylink\Enums\PayoutMethod;
use App\Payment\Easylink\Enums\TransferState;
use App\Payment\PaymentGatewayFactory;
use App\Services\Handlers\Interfaces\FailHandlerInterface;
use App\Services\Handlers\Interfaces\SubmittedHandlerInterface;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use App\Services\WalletService;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Services\TransactionService;

/**
 * WithdrawHandler class handles the processing of withdrawal requests.
 */
class WithdrawHandler implements FailHandlerInterface, SubmittedHandlerInterface, SuccessHandlerInterface
{
    /**
     * Handle processing of withdrawal request.
     *
     * @throws NotifyErrorException
     */
    public function handleProcessing(Transaction $transaction): void
    {
        if (empty($withdrawalAccount = $transaction?->trx_data['withdrawal_account'] ?? null)) {
            Log::error('error', __('Missing withdrawal account data'));
        }

        $bankId = $withdrawalAccount['account_bank_code'] ?? null;
        $accNo = $withdrawalAccount['account_number'] ?? null;
        $accName = $withdrawalAccount['account_holder_name'] ?? null;
        $payoutMethodCode = PayoutMethod::fromProviderName($transaction->provider);

        if ($payoutMethodCode === 0) {
            Log::error('error', __('Unsupported Easylink Payout Method for provider: :provider', ['provider' => $transaction->provider]));

        }

        if (! $bankId || ! $accNo || ! $accName) {
            Log::error('error', __('Missing required transaction data for EasyLink transfer.'));
        }

        $paymentGateway = app(PaymentGatewayFactory::class)->getGateway('easylink');

        $responseData = $paymentGateway->createDomesticTransfer($transaction);

        $trxData = array_merge(
            (array) $transaction->trx_data,
            ['easylink_disbursement' => (array) $responseData]
        );

        if ($responseData->state === TransferState::CREATE->value) {
            $transaction->update([
                'trx_data' => $trxData,
                'status' => TrxStatus::AWAITING_FI_PROCESS,
            ]);
            //            throw new \Exception('success', __('Successfully create Easylink Disbursement Request'));

        } elseif ($responseData->state === TransferState::CONFIRM->value) {
            throw NotifyErrorException::warning(
                __('Easylink Disbursement Request is in Confirm state. Please check Easylink Dashboard for more details.'),
                status: HttpResponse::HTTP_ACCEPTED,
                context: ['transaction_id' => $transaction->trx_id],
            );
            $trxFee = $responseData->fee ?? $transaction->trx_fee;
            $payableAmount = $responseData->source_amount ?? $transaction->payable_amount;
            $payableAmount = $payableAmount + $trxFee;

            // Adjustment Fee from Easylink
            if ($trxFee >= $transaction->trx_fee) {
                $adjustmentFee = $trxFee - $transaction->trx_fee;
                app(WalletService::class)->subtractMoneyByWalletUuid($transaction->wallet_reference, $adjustmentFee ?? 0);
            }

            // Update transaction status
            $transaction->update([
                'trx_data' => $trxData,
                'trx_fee' => $trxFee,
                'payable_amount' => $payableAmount,
                'status' => TrxStatus::AWAITING_FI_PROCESS,
            ]);

            return;
        } elseif ($responseData->state === TransferState::FAILED->value) {
            $transaction->update([
                'status' => TrxStatus::FAILED,
                'remarks' => 'Easylink Disbursement Failed: '.$responseData->message,
            ]);
            throw NotifyErrorException::error(
                __('Failed to create Easylink Disbursement Request: :message', ['message' => $responseData->message]),
                status: HttpResponse::HTTP_BAD_REQUEST,
                context: ['transaction_id' => $transaction->trx_id],
            );
        }

        if (in_array($responseData->state, [1, 2])) {
            Log::info('success', __('Successfully create Easylink Disbursement Request'));

            $trxData = array_merge(
                (array) $transaction->trx_data,
                ['easylink_disbursement' => (array) $responseData->data]
            );

            $transaction->update([
                'trx_data' => $trxData,
                'status' => TrxStatus::AWAITING_FI_PROCESS,
            ]);

            /**
             * TODO: Notify Admin and User
             */
        } elseif (empty($transaction->trx_data['easylink_disbursement'])) {
            Log::error('error', __('Duplicate Easylink Disbursement Request'));

            // Update transaction status
            $transaction->update([
                'status' => TrxStatus::AWAITING_FI_PROCESS,
            ]);
        } else {
            Log::error('error', __('Failed to create Easylink Disbursement Request'));
        }

        // Send webhook notification
        $transaction->refresh();
        app(WebhookService::class)->sendWithdrawalWebhook($transaction, 'withdrawal.processing');
    }

    /**
     * Handle check status of withdrawal request.
     */
    public function handleCheckStatus(Transaction $transaction): void
    {

        $settlement = $transaction->trx_data['easylink_settlement'] ?? null;

        if (! $settlement) {
            throw NotifyErrorException::warning(__('Withdrawal request still in process by Easylink'), status: HttpResponse::HTTP_ACCEPTED, context: ['transaction_id' => $transaction->trx_id]);

        }

        $state = (int) $settlement['state'];
        $this->handleSettlementState($state, $settlement);

    }

    /**
     * Handle different settlement states with appropriate notifications.
     */
    private function handleSettlementState(int $state, array $settlement): void
    {
        $stateEnum = TransferState::fromStatusCode($state);

        match ($state) {
            TransferState::COMPLETE->value => throw NotifyErrorException::success(__('Withdrawal disbursement has been settled by Easylink')),

            TransferState::FAILED->value => throw NotifyErrorException::error(__('Failed to create Easylink Disbursement Request: :message', ['message' => $responseData->message]), status: HttpResponse::HTTP_BAD_REQUEST, context: ['transaction_id' => $transaction->trx_id]),

            TransferState::CONFIRM->value => throw NotifyErrorException::warning(__('Withdrawal request is in confirmation state by Easylink'), status: HttpResponse::HTTP_ACCEPTED, context: ['transaction_id' => $transaction->trx_id]),

            TransferState::REVIEW->value => throw NotifyErrorException::warning(__('Withdrawal request is under review by Easylink'), status: HttpResponse::HTTP_ACCEPTED, context: ['transaction_id' => $transaction->trx_id]),

            default => throw NotifyErrorException::warning(__('Withdrawal request still in process by Easylink'), status: HttpResponse::HTTP_ACCEPTED, context: ['transaction_id' => $transaction->trx_id])
        };
    }

    /**
     * Handle failed state with detailed reason information.
     */
    public function handleSuccess(Transaction $transaction): void
    {
        $trxData = $transaction->trx_data;

        if (isset($trxData['reference'])) {
            $paymentGateway = app(PaymentGatewayFactory::class)->getGateway('easylink');

            if ($responseData = $paymentGateway->getDomesticTransfer($trxData['reference'])) {
                $trxData = array_merge((array) $transaction->trx_data, $responseData);

                $transaction->update([
                    'trx_data' => $trxData,
                ]);

                app(WalletService::class)->subtractMoneyByWalletUuid($transaction->wallet_reference, $transaction->payable_amount);

                // Send webhook notification
                app(WebhookService::class)->sendWithdrawalWebhook($transaction, 'withdrawal.completed');

            }
        }
    }

    /**
     * Handle failed withdrawal request.
     */
    public function handleFail(Transaction $transaction): void
    {
        // Send webhook notification
        app(WebhookService::class)->sendWithdrawalWebhook($transaction, 'withdrawal.failed');

    }

    /**
     * Handle submitted withdrawal request.
     */
    public function handleSubmitted(Transaction $transaction): void
    {
        // Send webhook notification
        app(WebhookService::class)->sendWithdrawalWebhook($transaction, 'withdrawal.submitted');
    }
}
