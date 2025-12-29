<?php

namespace App\Services\Handlers;

use App\Jobs\UpdateAggregatorStoreDailyCacheJob;
use App\Jobs\UpdateTransactionStatJob;
use App\Models\Wallet;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use Illuminate\Support\Facades\Bus;
use Wrpay\Core\Models\Transaction;

class PaymentHandler implements SuccessHandlerInterface
{
    /**
     * Handle success of payment request.
     */
    public function handleSuccess(Transaction $transaction): bool
    {
        // If this is a RECEIVE_PAYMENT, add to balance and lock funds in hold
        $wallet = Wallet::where('uuid', $transaction->wallet_reference)->first();
        if (! $wallet) {
            return false;
        }

        $amount = (float) ($transaction->net_amount ?? 0);

        if ($amount <= 0) {
            return false;
        }

        $added = $wallet->addToHoldBalance($amount);

        if (! $added) {
            return false;
        }

        Bus::batch([
            new UpdateTransactionStatJob($transaction),
            new UpdateAggregatorStoreDailyCacheJob($transaction),
        ])->dispatch();

        return true;

    }
}
