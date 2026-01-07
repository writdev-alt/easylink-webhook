<?php

namespace App\Services\Handlers;

use App\Jobs\UpdateAggregatorStoreDailyCacheJob;
use App\Jobs\UpdateTransactionStatJob;
use App\Services\Handlers\Interfaces\FailHandlerInterface;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Wrpay\Core\Models\Transaction;
use Wrpay\Core\Services\WalletService;
use Wrpay\Core\Services\WebhookService;

/**
 * WithdrawHandler class handles the processing of withdrawal requests.
 */
class WithdrawHandler implements FailHandlerInterface
{
    /**
     * Handle successful withdrawal: merge gateway data, subtract funds, send webhook.
     */
    public function handleSuccess(Transaction $transaction): bool
    {
        Bus::batch([
            new UpdateTransactionStatJob($transaction),
            new UpdateAggregatorStoreDailyCacheJob($transaction),
        ])->dispatch();

        if ($transaction->uuid === null) {
            $transaction->uuid = Str::uuid()->toString();
            $transaction->saveQuietly();
        }

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
