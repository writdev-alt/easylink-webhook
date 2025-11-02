<?php

namespace App\Services\Handlers;

use App\Models\Transaction;
use App\Services\Handlers\Interfaces\FailHandlerInterface;
use App\Services\Handlers\Interfaces\SubmittedHandlerInterface;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use App\Services\WalletService;

/**
 * DepositHandler class handles the processing of deposit requests.
 */
class DepositHandler implements FailHandlerInterface, SubmittedHandlerInterface, SuccessHandlerInterface
{
    public function handleSuccess(Transaction $transaction): void
    {
        app(WalletService::class)->addMoneyByWalletUuid($transaction->wallet_reference, $transaction->net_amount);

    }

    /**
     * Handle fail of deposit request.
     */
    public function handleFail(Transaction $transaction): void {}

    /**
     * Handle submitted of deposit request.
     */
    public function handleSubmitted(Transaction $transaction): void {}
}
