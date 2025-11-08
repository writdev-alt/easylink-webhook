<?php

namespace App\Services\Handlers;

use App\Enums\TrxType;
use App\Jobs\UpdateTransactionStatJob;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use App\Services\WebhookService;

class PaymentHandler implements SuccessHandlerInterface
{
    /**
     * Handle success of payment request.
     */
    public function handleSuccess(Transaction $transaction): bool
    {
        // If this is a RECEIVE_PAYMENT, add to balance and lock funds in hold
        if ($transaction->trx_type === TrxType::RECEIVE_PAYMENT) {
            $wallet = Wallet::where('uuid', $transaction->wallet_reference)->first();
            if ($wallet) {
                $amount = $transaction->net_amount;

                UpdateTransactionStatJob::dispatch($transaction->merchant, $amount, $transaction->trx_type);
                UpdateTransactionStatJob::dispatch($transaction->user, $amount, $transaction->trx_type);

                return $wallet->addToHoldBalance($amount);
            }

            return false;
        }

        return app(WebhookService::class)->sendPaymentReceiveWebhook($transaction, 'Payment received');

    }
}
