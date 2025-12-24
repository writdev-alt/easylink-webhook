<?php

namespace App\Services\Handlers;

use App\Jobs\UpdateTransactionStatJob;
use App\Jobs\UpdateAggregatorStoreDailyCacheJob;
use App\Models\Wallet;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
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
        if ($wallet) {
            $amount = $transaction->net_amount;

            UpdateTransactionStatJob::dispatch($transaction);
            UpdateAggregatorStoreDailyCacheJob::dispatch($transaction);

            return $wallet->addToHoldBalance($amount);
        }

        return false;

    }
}
