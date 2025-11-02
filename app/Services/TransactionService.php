<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Exceptions\NotifyErrorException;
use App\Facades\WalletFacade;
use App\Models\Transaction;
use App\Services\Handlers\DepositHandler;
use App\Services\Handlers\Interfaces\FailHandlerInterface;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use App\Services\Handlers\PaymentHandler;
use App\Services\Handlers\WithdrawHandler;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * TransactionService
 *
 * Centralized service for managing all transaction-related operations including
 * creation, processing, status updates, and statistics. Handles various transaction
 * types like deposits, withdrawals, payments, and money transfers.
 *
 * @author WRPay Team
 *
 * @version 1.0.0
 */
class TransactionService
{
    /**
     * Completes a transaction and triggers the success handler
     *
     * Marks a transaction as completed and executes the appropriate success handler.
     * Includes IPN notifications for merchant payments and event dispatching.
     *
     * @param  string  $trxId  The unique ID of the transaction
     * @param  string|null  $remarks  Optional remarks for the transaction update
     * @param  string|null  $description  Optional description for the transaction update
     *
     * @throws \Throwable
     *
     * @example
     * $transactionService->completeTransaction('TXN123456789', 'Payment successful', 'Customer payment completed');
     */
    public function completeTransaction(string $trxId, ?string $remarks = null, ?string $description = null): void
    {
        $transaction = $this->findTransaction($trxId);

        if (! $transaction) {
            throw NotifyErrorException::error(
                __('Transaction not found for ID: :id', ['id' => $trxId]),
                context: ['transaction_id' => $trxId],
                status: HttpResponse::HTTP_NOT_FOUND,
            );
        }

        $this->updateTransactionStatusWithRemarks($transaction, TrxStatus::COMPLETED, $remarks, $description);

        if (($handler = $this->resolveHandler($transaction)) instanceof SuccessHandlerInterface) {
            $handler->handleSuccess($transaction);
        }
    }

    /**
     * Finds a transaction by its unique transaction ID
     *
     * Retrieves a transaction from the database using its unique transaction ID.
     *
     * @param  string  $trxId  The unique transaction ID
     * @return Transaction|null The found transaction model or null if not found
     *
     * @example
     * $transaction = $transactionService->findTransaction('TXN123456789');
     */
    public function findTransaction(string $trxId): ?Transaction
    {
        return Transaction::where('trx_id', $trxId)->first();
    }

    /**
     * Updates a transaction's status and remarks.
     *
     * @param  Transaction  $transaction  The transaction model to update.
     * @param  TrxStatus  $status  The new transaction status.
     * @param  string|null  $remarks  Optional remarks for the update.
     * @param  string|null  $description  Optional description for the update.
     */
    protected function updateTransactionStatusWithRemarks(Transaction $transaction, TrxStatus $status, ?string $remarks = null, ?string $description = null): void
    {
        $transaction->update(array_filter([
            'status' => $status,
            'remarks' => $remarks,
            'description' => $description,
        ]));

        $transaction->status = $status;
        $transaction->remarks = $remarks;
        $transaction->description = $description;

        $transaction->save();
        $transaction->refresh();
    }


    /**
     * Fails a transaction and triggers the failure handler.
     *
     * @param  string  $trxId  The unique ID of the transaction.
     * @param  string|null  $remarks  Optional remarks for the transaction update.
     * @param  string|null  $description  Optional description for the transaction update.
     *
     * @throws NotifyErrorException
     */
    public function failTransaction(string $trxId, ?string $remarks = null, ?string $description = null): void
    {
        $transaction = $this->findTransaction($trxId);

        if (! $transaction) {
            throw new \Exception("Transaction not found for ID: {$trxId}");
        }

        $this->updateTransactionStatusWithRemarks($transaction, TrxStatus::FAILED, $remarks, $description);

        if ($transaction->trx_type === TrxType::RECEIVE_PAYMENT) {
            // Refund the user if the transaction is a receive payment
            app(WebhookService::class)->sendPaymentReceiveWebhook($transaction, 'Payment Failed');
        } elseif ($transaction->trx_type === TrxType::WITHDRAW) {
            // Refund the user if the transaction is a withdrawal
            app(WalletService::class)->addMoneyByWalletUuid($transaction->wallet_reference, $transaction->payable_amount);
        }

        if (($handler = $this->resolveHandler($transaction)) instanceof FailHandlerInterface) {
            $handler->handleFail($transaction);
        }
    }

    /**
     * Cancels a transaction, with an option to refund the amount.
     *
     * @param  string  $trxId  The unique ID of the transaction.
     * @param  string|null  $remarks  Optional remarks for the transaction update.
     * @param  bool  $refund  Flag to determine if a refund should be issued.
     *
     * @throws NotifyErrorException
     */
    public function cancelTransaction(string $trxId, ?string $remarks = null, bool $refund = false): void
    {
        $transaction = $this->findTransaction($trxId);

        if (! $transaction) {
            throw new \Exception("Transaction not found for ID: {$trxId}");
        }

        if ($refund) {
            app(WalletService::class)::addMoneyByWalletUuid($transaction->wallet_reference, $transaction->payable_amount);
        }

        $this->updateTransactionStatusWithRemarks($transaction, TrxStatus::CANCELED, $remarks);
        $transaction->status = TrxStatus::CANCELED;

        if (($handler = $this->resolveHandler($transaction)) instanceof FailHandlerInterface) {
            $handler->handleFail($transaction);
        }
    }

    /**
     * Resolves a transaction handler based on its type.
     *
     * @param  Transaction  $transaction  The transaction model.
     * @return object|null The resolved handler instance or null if no handler is found.
     */
    protected function resolveHandler(Transaction $transaction): ?object
    {
        return match ($transaction->trx_type) {
            TrxType::DEPOSIT => app(DepositHandler::class),
            TrxType::RECEIVE_PAYMENT => app(PaymentHandler::class),
            TrxType::WITHDRAW => app(WithdrawHandler::class),
            default => null,
        };
    }
}
