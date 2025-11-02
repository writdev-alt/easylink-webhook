<?php

declare(strict_types=1);

namespace App\Payment\Easylink;

use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Models\Transaction;
use App\Payment\Easylink\Enums\TransferState;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service class for interacting with the EasyLink API.
 *
 * This class handles all communication with the EasyLink service, including
 * authentication, signature generation, and API calls for various transactions.
 */
class EasylinkPaymentGateway
{
    /**
     * Handles the disbursement response from the EasyLink API.
     *
     * @param  Request  $request  The request object.
     * @return bool Returns true if the transaction was handled successfully, false otherwise.
     *
     * @throws \Throwable
     */
    public function handleDisbursment(Request $request): bool
    {
        if ($transaction = Transaction::where(['trx_id' => $request->reference_id, 'trx_type' => TrxType::WITHDRAW])->first()) {
            $data = array_merge($transaction->trx_data ?? [], [
                'easylink_settlement' => $request->toArray() ?? [],
            ]);

            $transaction->update([
                'trx_data' => $data,
            ]);

            $handled = match ((int) $request->state) {
                TransferState::COMPLETE->value => tap(true, function () use ($request): void {
                    app(TransactionService::class)->completeTransaction($request->reference_id);

                }),

                TransferState::FAILED->value => tap(true, function () use ($request): void {
                    app(TransactionService::class)->failTransaction($request->reference_id, 'Easylink Disbursement Failed', 'Withdrawal failed');
                }),

                TransferState::REFUND_SUCCESS->value => tap(true, function () use ($request): void {
                    app(TransactionService::class)->failTransaction($request->reference_id, 'Easylink Disbursement Refunded', 'Withdrawal refunded');
                }),

                default => false,
            };

            return $handled;
        }

        return false;
    }

    /**
     * Handles the topup response from the EasyLink API.
     *
     * @param  Request  $request  The request object.
     * @return bool Returns true if the transaction was handled successfully, false otherwise.
     *
     * @throws \Throwable
     */
    public function handleTopup(Request $request): bool
    {
        if ($transaction = Transaction::where('trx_id', $request->reference_id)->first()) {
            $data = array_merge($transaction->trx_data ?? [], [
                'easylink_topup' => (array) $request->all() ?? [],
            ]);

            $transaction->update(['trx_data' => $data]);

            $handled = match ((int) $request->state) {
                TransferState::COMPLETE->value => tap(true, function () use ($transaction, $request): void {
                    if ($transaction->status !== TrxStatus::COMPLETED) {
                        app(TransactionService::class)->completeTransaction($request->reference_id);
                    }
                }),

                TransferState::FAILED->value => tap(true, function () use ($request): void {
                    app(TransactionService::class)->failTransaction($request->reference_id, 'Easylink Topup Failed', 'Topup failed');
                }),

                TransferState::REFUND_SUCCESS->value => tap(true, function () use ($request): void {
                    app(TransactionService::class)->failTransaction($request->reference_id, 'Easylink Topup Refunded', 'Topup refunded');
                }),

                default => false,
            };

            return $handled;
        }

        return false;
    }
}
