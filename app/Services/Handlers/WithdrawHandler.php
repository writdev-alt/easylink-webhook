<?php

namespace App\Services\Handlers;

use App\Jobs\UpdateTransactionStatJob;
use App\Models\Transaction;
use App\Payment\PaymentGatewayFactory;
use App\Services\Handlers\Interfaces\FailHandlerInterface;
use App\Services\Handlers\Interfaces\SubmittedHandlerInterface;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use App\Services\WalletService;
use App\Services\WebhookService;

/**
 * WithdrawHandler class handles the processing of withdrawal requests.
 */
class WithdrawHandler implements FailHandlerInterface, SubmittedHandlerInterface, SuccessHandlerInterface
{
    /**
     * Handle successful withdrawal: merge gateway data, subtract funds, send webhook.
     */
    public function handleSuccess(Transaction $transaction): bool
    {
        // Merge gateway disbursement data into trx_data using reference
        $reference = is_array($transaction->trx_data ?? null) ? ($transaction->trx_data['reference'] ?? null) : null;
        if ($reference) {
            $gateway = app(PaymentGatewayFactory::class)->getGateway('easylink');
            $payload = $gateway->getDomesticTransfer($reference);

            $merged = array_merge(['reference' => $reference], (array) $payload);
            $transaction->update(['trx_data' => $merged]);
        }

        // Subtract payable amount from wallet
        app(WalletService::class)->subtractMoneyByWalletUuid(
            $transaction->wallet_reference,
            (float) $transaction->payable_amount
        );

        UpdateTransactionStatJob::dispatch($transaction);
        // Notify webhook as completed
        app(WebhookService::class)->sendWithdrawalWebhook($transaction, 'withdrawal completed');

        return true;
    }

    /**
     * Handle failed withdrawal request.
     */
    public function handleFail(Transaction $transaction): bool
    {
        // Subtract payable amount from wallet
        app(WalletService::class)->subtractMoneyByWalletUuid(
            $transaction->wallet_reference,
            (float) $transaction->payable_amount
        );

        app(WebhookService::class)->sendWithdrawalWebhook($transaction, 'withdrawal failed');

        return true;
    }

    /**
     * Handle submitted withdrawal request.
     */
    public function handleSubmitted(Transaction $transaction): bool
    {
        return app(WebhookService::class)->sendWithdrawalWebhook($transaction, 'withdrawal submitted');
    }
}
