<?php

namespace App\Payment\Netzme;

use App\Payment\PaymentGateway;
use App\Services\Handlers\PaymentHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Wrpay\Core\Enums\TrxStatus;
use Wrpay\Core\Enums\TrxType;
use Wrpay\Core\Models\Transaction;
use Wrpay\Core\Services\TransactionService;

/**
 * Class NetzmePaymentGateway
 *
 * This class implements the PaymentGateway interface for the Netzme payment service.
 * It handles the creation of deposits and processing of IPN (Instant Payment Notification) callbacks.
 */
class NetzmePaymentGateway implements PaymentGateway
{
    /**
     * Handles the IPN callback from the Netzme payment gateway.
     *
     * @param  Request  $request  The incoming request containing the IPN data.
     * @return bool A JSON response indicating the status.
     *
     * @throws \Throwable
     */
    public function handleIPN(Request $request): bool
    {
        if ($transaction = Transaction::where('trx_id', $request->originalPartnerReferenceNo)
            ->whereIn('trx_type', [TrxType::RECEIVE_PAYMENT, TrxType::DEPOSIT])
            ->first()) {
            
            // If success, complete and send second webhook using same instance
            if ($request->transactionStatusDesc === 'Success' && $request->latestTransactionStatus === '00') {

                Log::info('Netzme transaction IPN hit', [
                    'trx_id' => $transaction->trx_id,
                    'transaction_status' => $request->transactionStatusDesc ?? null,
                    'latest_status' => $request->latestTransactionStatus ?? null,
                ]);

                if ($transaction->status !== TrxStatus::COMPLETED) {
                    $rrn = $request->additionalInfo['rrn'];
                    $paidAt = $request->additionalInfo['paymentTime'];
                    $description = $transaction->trx_type === TrxType::DEPOSIT
                        ? 'Deposit completed via QRIS IPN'
                        : 'Receive Payment completed via QRIS IPN';

                    app(PaymentHandler::class)->handleSuccess($transaction);

                    return app(TransactionService::class)->completeTransaction(
                        trxId: $request->originalPartnerReferenceNo,
                        referenceNumber: $rrn,
                        remarks: 'Transaction completed via QRIS IPN',
                        description: $description
                    );
                }

                return true;
            }

            // For non-success statuses, dispatch was done; update tracking and return
            Log::info('Netzme transaction IPN hit (non-success)', [
                'trx_id' => $transaction->trx_id,
                'transaction_status' => $request->transactionStatusDesc ?? null,
                'latest_status' => $request->latestTransactionStatus ?? null,
            ]);

            return true;
        }

        return false;
    }
}
