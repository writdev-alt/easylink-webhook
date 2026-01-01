<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Wrpay\Core\Enums\TrxType;
use Wrpay\Core\Models\Transaction;
use Wrpay\Core\Services\WebhookService;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhookService) {}

    /**
     * Resend webhook for a given transaction.
     *
     * @param Request $request
     * @param string $trx_id Transaction ID
     * @return JsonResponse
     */
    public function resend(Request $request, string $trx_id): JsonResponse
    {
        try {
            /** @var Transaction|null $transaction */
            $transaction = Transaction::query()->where('trx_id', $trx_id)->first();

            if (! $transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => sprintf('Transaction not found for trx_id %s', $trx_id),
                ], 404);
            }

            Log::info('Webhook resend initiated', [
                'trx_id' => $transaction->trx_id,
                'trx_type' => $transaction->trx_type->value,
                'triggered_by' => 'api',
                'ip' => $request->ip(),
            ]);

            try {
                $sent = match ($transaction->trx_type) {
                    TrxType::RECEIVE_PAYMENT => $this->webhookService->sendPaymentReceiveWebhook($transaction),
                    TrxType::WITHDRAW => $this->webhookService->sendWithdrawalWebhook($transaction),
                    default => $this->webhookService->sendGenericWebhook($transaction),
                };
            } catch (\Throwable $exception) {
                Log::error('Webhook resend failed', [
                    'trx_id' => $transaction->trx_id,
                    'trx_type' => $transaction->trx_type->value,
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                    'trace' => $exception->getTraceAsString(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => sprintf('Webhook dispatch failed: %s', $exception->getMessage()),
                ], 500);
            }

            if ($sent) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Webhook dispatched successfully',
                    'data' => [
                        'trx_id' => $transaction->trx_id,
                        'trx_type' => $transaction->trx_type->value,
                    ],
                ], 200);
            }

            return response()->json([
                'status' => 'warning',
                'message' => 'Webhook dispatch skipped or failed. Check logs for details.',
                'data' => [
                    'trx_id' => $transaction->trx_id,
                    'trx_type' => $transaction->trx_type->value,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Webhook resend endpoint error', [
                'trx_id' => $trx_id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
            ], 500);
        }
    }
}
