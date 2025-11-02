<?php

namespace App\Services\Handlers;

use App\Enums\TrxStatus;
use App\Exceptions\NotifyErrorException;
use App\Models\Transaction;
use App\Payment\Easylink\Enums\PayoutMethod;
use App\Payment\Easylink\Enums\TransferState;
use App\Payment\PaymentGatewayFactory;
use App\Services\Handlers\Interfaces\FailHandlerInterface;
use App\Services\Handlers\Interfaces\SubmittedHandlerInterface;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use App\Services\WalletService;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * WithdrawHandler class handles the processing of withdrawal requests.
 */
class WithdrawHandler implements FailHandlerInterface, SubmittedHandlerInterface, SuccessHandlerInterface
{

    /**
     * Handle failed state with detailed reason information.
     */
    public function handleSuccess(Transaction $transaction): bool
    {
        app(WebhookService::class)->sendWithdrawalWebhook($transaction, 'withdrawal successful');
        $wallet = app(WalletService::class)->subtractMoneyByWalletUuid($transaction->wallet_reference, $transaction->payable_amount);
        if ($wallet) {
            return true;
        }
        return false;
    }

    /**
     * Handle failed withdrawal request.
     */
    public function handleFail(Transaction $transaction): bool
    {
        // Send webhook notification
        app(WebhookService::class)->sendWithdrawalWebhook($transaction, 'withdrawal failed');
        // Refund the amount to user's wallet
        $wallet = app(WalletService::class)->addMoneyByWalletUuid($transaction->wallet_reference, $transaction->payable_amount);
        if ($wallet) {
            return true;
        }
        return false;
    }

    /**
     * Handle submitted withdrawal request.
     */
    public function handleSubmitted(Transaction $transaction): bool
    {
        // Send webhook notification
        return app(WebhookService::class)->sendWithdrawalWebhook($transaction, 'withdrawal submitted');
    }
}
