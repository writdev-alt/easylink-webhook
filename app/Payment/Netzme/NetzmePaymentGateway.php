<?php

namespace App\Payment\Netzme;

use App\Enums\TrxStatus;
use App\Models\Transaction;
use App\Payment\PaymentGateway;
use App\Services\TransactionService;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

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
     * @return \Illuminate\Http\JsonResponse A JSON response indicating the status.
     *
     * @throws \Throwable
     */
    public function handleIPN(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($transaction = Transaction::where('trx_id', $request->originalPartnerReferenceNo)->whereIn('trx_type', )->first()) {
            $data = array_merge($transaction->trx_data ?? [], [
                'netzme_ipn_response' => (array) $request->all() ?? [],
            ]);
            $transaction->update(['trx_data' => $data]);
            $transaction->save();
            $transaction->refresh();

            app(TransactionService::class)->completeTransaction($request->originalPartnerReferenceNo);
            app(WebhookService::class)->sendPaymentReceiveWebhook($transaction);

            if ($request->transactionStatusDesc === 'Success' && $request->latestTransactionStatus === '00') {
                if ($transaction->status !== TrxStatus::COMPLETED) {
                    app(TransactionService::class)->completeTransaction($request->originalPartnerReferenceNo);
                    app(WebhookService::class)->sendPaymentReceiveWebhook($transaction);
                }
                return response()->json(['status' => 'success']);
            }

        }

        return response()->json(['status' => 'failed'], Response::HTTP_BAD_REQUEST);
    }
}
