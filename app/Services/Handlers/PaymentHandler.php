<?php

namespace App\Services\Handlers;

use App\Enums\AmountFlow;
use App\Enums\TrxType;
use App\Models\Transaction;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use App\Services\WalletService;

class PaymentHandler implements SuccessHandlerInterface
{
    /**
     * Handle success of payment request.
     */
    public function handleSuccess(Transaction $transaction): void
    {
        if ($transaction->amount_flow === AmountFlow::MINUS) {
            app(WalletService::class)->subtractMoneyByWalletUuid($transaction->wallet_reference, $transaction->payable_amount);

        }

        if ($transaction->amount_flow === AmountFlow::PLUS) {
            // For RECEIVE_PAYMENT, add funds and place them on hold until H+1 release
            if ($transaction->trx_type === TrxType::RECEIVE_PAYMENT) {
                // Add to total balance first
                app(WalletService::class)->addMoneyByWalletUuid($transaction->wallet_reference, $transaction->net_amount);

                // Then increase hold balance via model helper to lock funds
                $wallet = \App\Models\Wallet::where('uuid', $transaction->wallet_reference)->first();
                if ($wallet) {
                    $wallet->addToHoldBalance($transaction->net_amount);
                }
            } else {
                app(WalletService::class)->addMoneyByWalletUuid($transaction->wallet_reference, $transaction->net_amount);
            }
        }
    }
}
