<?php

namespace App\Services\Handlers;

use App\Jobs\UpdateTransactionStatJob;
use App\Models\Transaction;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use App\Services\WalletService;
use App\Services\WebhookService;

/**
 * DepositHandler class handles the processing of deposit requests.
 */
class DepositHandler implements SuccessHandlerInterface
{
    public function handleSuccess(Transaction $transaction): bool
    {

        $wallet = app(WalletService::class)->addMoneyByWalletUuid($transaction->wallet_reference, $transaction->net_amount);
        if ($wallet) {
            UpdateTransactionStatJob::dispatch($transaction);

            return true;
        }

        return app(WebhookService::class)->sendPaymentReceiveWebhook($transaction, 'Deposit Completed');

    }
}
