<?php

namespace App\Services\Handlers;

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

        app(WebhookService::class)->sendPaymentReceiveWebhook($transaction, 'Deposit Completed');
        $wallet = app(WalletService::class)->addMoneyByWalletUuid($transaction->wallet_reference, $transaction->net_amount);
        if ($wallet) {
            return true;
        }

        return false;

    }
}
