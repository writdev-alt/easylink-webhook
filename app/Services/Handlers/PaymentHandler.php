<?php

namespace App\Services\Handlers;

use App\Models\Transaction;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use App\Services\WalletService;

class PaymentHandler implements SuccessHandlerInterface
{
    /**
     * Handle success of payment request.
     */
    public function handleSuccess(Transaction $transaction): bool
    {
        // Add to total balance first
        app(WalletService::class)->addMoneyByWalletUuid($transaction->wallet_reference, $transaction->net_amount);

        // Then increase hold balance via model helper to lock funds
        $wallet = \App\Models\Wallet::where('uuid', $transaction->wallet_reference)->first();
        if ($wallet) {
            return $wallet->addToHoldBalance($transaction->net_amount);
        }
        return false;
    }
}
