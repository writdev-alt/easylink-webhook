<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Models\Transaction;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookServer\WebhookCall;

/**
 * WebhookService
 *
 * Centralized service for sending webhooks for payment receipt and withdrawal transactions.
 * Handles webhook dispatching with proper signature verification and error handling.
 *
 * @author WRPay Team
 *
 * @version 1.0.0
 */
class WebhookService
{
    /**
     * Send webhook for a transaction based on its type
     *
     * Automatically determines the webhook type based on transaction type
     * and dispatches the appropriate webhook notification.
     *
     * @param  Transaction  $transaction  The transaction to send webhook for
     * @param  string|null  $message  Optional custom message
     * @return bool Returns true if webhook was sent successfully, false otherwise
     *
     * @example
     * $webhookService->sendWebhook($transaction, 'Payment received successfully');
     */
    public function sendWebhook(Transaction $transaction, ?string $message = null): bool
    {
        // Check if webhooks are enabled for this transaction
        if (! $transaction->isWebhookEnabled()) {
            Log::channel('webhook')->info('Webhook skipped: webhooks not enabled', [
                'trx_id' => $transaction->trx_id,
                'trx_type' => $transaction->trx_type->value,
            ]);

            return false;
        }

        // Check if webhook was already sent
        if ($transaction->alreadySentAutomaticWebhook()) {
            $trxData = $transaction->trx_data ?? [];
            $attemptCount = ($trxData['webhook_attempts'] ?? 0) + 1;

            // Update attempt count
            $trxData['webhook_attempts'] = $attemptCount;
            $trxData['webhook_attempt_history'][] = [
                'attempt' => $attemptCount,
                'timestamp' => now()->toIso8601String(),
                'status' => 'duplicate_attempt',
                'message' => 'Webhook already sent, duplicate attempt blocked',
            ];
            $transaction->update(['trx_data' => $trxData]);

            Log::channel('webhook')->warning('Webhook duplicate attempt blocked', [
                'trx_id' => $transaction->trx_id,
                'webhook_call_at' => $transaction->webhook_call,
                'attempt_count' => $attemptCount,
                'trx_type' => $transaction->trx_type->value,
            ]);

            return false;
        }

        // Route to appropriate webhook handler based on transaction type
        return match ($transaction->trx_type) {
            TrxType::RECEIVE_PAYMENT => $this->sendPaymentReceiveWebhook($transaction, $message),
            TrxType::WITHDRAW => $this->sendWithdrawalWebhook($transaction, $message),
            default => $this->sendGenericWebhook($transaction, $message),
        };
    }

    /**
     * Send webhook for payment receipt transactions
     *
     * Dispatches webhook notification when a merchant receives a payment.
     * Includes comprehensive payment details and customer information.
     *
     * @param  Transaction  $transaction  The payment transaction
     * @param  string|null  $message  Optional custom message
     * @return bool Returns true if webhook was sent successfully
     *
     * @example
     * $webhookService->sendPaymentReceiveWebhook($transaction, 'Payment Completed');
     */
    public function sendPaymentReceiveWebhook(Transaction $transaction, string $rrn, ?string $message = null): bool
    {
        $trxData = $transaction->trx_data ?? [];
        $attemptCount = ($trxData['webhook_attempts'] ?? 0) + 1;

        try {
            $webhookConfig = $transaction->getWebhookConfig();

            if (empty($webhookConfig['url']) || empty($webhookConfig['secret'])) {
                // Update attempt tracking
                $trxData['webhook_attempts'] = $attemptCount;
                $trxData['webhook_attempt_history'][] = [
                    'attempt' => $attemptCount,
                    'timestamp' => now()->toIso8601String(),
                    'status' => 'failed',
                    'reason' => 'missing_configuration',
                    'has_url' => ! empty($webhookConfig['url']),
                    'has_secret' => ! empty($webhookConfig['secret']),
                ];
                $transaction->update(['trx_data' => $trxData]);

                Log::channel('webhook')->warning('Webhook skipped: missing configuration', [
                    'trx_id' => $transaction->trx_id,
                    'has_url' => ! empty($webhookConfig['url']),
                    'has_secret' => ! empty($webhookConfig['secret']),
                    'attempt_count' => $attemptCount,
                ]);

                return false;
            }

            // Prepare webhook payload with payment details
            $payload = $this->buildPaymentPayload($transaction, $rrn, $message);

            // Send webhook using Spatie Webhook Server
            $this->dispatchWebhook(
                url: $webhookConfig['url'],
                secret: $webhookConfig['secret'],
                payload: $payload,
                transaction: $transaction
            );

            // Mark webhook as sent
            $transaction->setWebhookCall();

            // Update attempt tracking for success
            $trxData['webhook_attempts'] = $attemptCount;
            $trxData['webhook_attempt_history'][] = [
                'attempt' => $attemptCount,
                'timestamp' => now()->toIso8601String(),
                'status' => 'success',
                'webhook_url' => $webhookConfig['url'],
            ];
            $transaction->update(['trx_data' => $trxData]);

            Log::channel('webhook')->info('Payment webhook sent successfully', [
                'trx_id' => $transaction->trx_id,
                'webhook_url' => $webhookConfig['url'],
                'status' => $transaction->status->value,
                'attempt_count' => $attemptCount,
            ]);

            return true;
        } catch (\Exception $e) {
            // Get webhook config if available (might not be set if exception occurred earlier)
            $webhookConfig = $webhookConfig ?? $transaction->getWebhookConfig();

            // Update attempt tracking for failure
            $trxData['webhook_attempts'] = $attemptCount;
            $trxData['webhook_attempt_history'][] = [
                'attempt' => $attemptCount,
                'timestamp' => now()->toIso8601String(),
                'status' => 'failed',
                'reason' => 'exception',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ];
            $transaction->update(['trx_data' => $trxData]);

            Log::channel('webhook')->error('Payment webhook failed', [
                'trx_id' => $transaction->trx_id,
                'attempt_count' => $attemptCount,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'webhook_url' => $webhookConfig['url'] ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send webhook for withdrawal transactions
     *
     * Dispatches webhook notification for withdrawal requests and status updates.
     * Includes withdrawal details and account information.
     *
     * @param  Transaction  $transaction  The withdrawal transaction
     * @param  string|null  $message  Optional custom message
     * @return bool Returns true if webhook was sent successfully
     *
     * @example
     * $webhookService->sendWithdrawalWebhook($transaction, 'Withdrawal Completed');
     */
    public function sendWithdrawalWebhook(Transaction $transaction, ?string $message = null): bool
    {
        $trxData = $transaction->trx_data ?? [];
        $attemptCount = ($trxData['webhook_attempts'] ?? 0) + 1;

        try {
            $webhookConfig = $transaction->getWebhookConfig();

            if (empty($webhookConfig['url']) || empty($webhookConfig['secret'])) {
                // Update attempt tracking
                $trxData['webhook_attempts'] = $attemptCount;
                $trxData['webhook_attempt_history'][] = [
                    'attempt' => $attemptCount,
                    'timestamp' => now()->toIso8601String(),
                    'status' => 'failed',
                    'reason' => 'missing_configuration',
                    'has_url' => ! empty($webhookConfig['url']),
                    'has_secret' => ! empty($webhookConfig['secret']),
                ];
                $transaction->update(['trx_data' => $trxData]);

                Log::channel('webhook')->warning('Webhook skipped: missing configuration', [
                    'trx_id' => $transaction->trx_id,
                    'has_url' => ! empty($webhookConfig['url']),
                    'has_secret' => ! empty($webhookConfig['secret']),
                    'attempt_count' => $attemptCount,
                ]);

                return false;
            }

            // Prepare webhook payload with withdrawal details
            $payload = $this->buildWithdrawalPayload($transaction, $message);

            // Send webhook using Spatie Webhook Server
            $this->dispatchWebhook(
                url: $webhookConfig['url'],
                secret: $webhookConfig['secret'],
                payload: $payload,
                transaction: $transaction
            );

            // Mark webhook as sent
            $transaction->setWebhookCall();

            // Update attempt tracking for success
            $trxData['webhook_attempts'] = $attemptCount;
            $trxData['webhook_attempt_history'][] = [
                'attempt' => $attemptCount,
                'timestamp' => now()->toIso8601String(),
                'status' => 'success',
                'webhook_url' => $webhookConfig['url'],
            ];
            $transaction->update(['trx_data' => $trxData]);

            Log::channel('webhook')->info('Withdrawal webhook sent successfully', [
                'trx_id' => $transaction->trx_id,
                'webhook_url' => $webhookConfig['url'],
                'status' => $transaction->status->value,
                'attempt_count' => $attemptCount,
            ]);

            return true;
        } catch (\Exception $e) {
            // Get webhook config if available (might not be set if exception occurred earlier)
            $webhookConfig = isset($webhookConfig) ? $webhookConfig : $transaction->getWebhookConfig();

            // Update attempt tracking for failure
            $trxData['webhook_attempts'] = $attemptCount;
            $trxData['webhook_attempt_history'][] = [
                'attempt' => $attemptCount,
                'timestamp' => now()->toIso8601String(),
                'status' => 'failed',
                'reason' => 'exception',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ];
            $transaction->update(['trx_data' => $trxData]);

            Log::channel('webhook')->error('Withdrawal webhook failed', [
                'trx_id' => $transaction->trx_id,
                'attempt_count' => $attemptCount,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'webhook_url' => $webhookConfig['url'] ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send generic webhook for other transaction types
     *
     * Provides a fallback webhook handler for transaction types
     * that don't have specialized webhook handling.
     *
     * @param  Transaction  $transaction  The transaction
     * @param  string|null  $message  Optional custom message
     * @return bool Returns true if webhook was sent successfully
     */
    public function sendGenericWebhook(Transaction $transaction, ?string $message = null): bool
    {
        $trxData = $transaction->trx_data ?? [];
        $attemptCount = ($trxData['webhook_attempts'] ?? 0) + 1;

        try {
            $webhookConfig = $transaction->getWebhookConfig();

            if (empty($webhookConfig['url']) || empty($webhookConfig['secret'])) {
                // Update attempt tracking
                $trxData['webhook_attempts'] = $attemptCount;
                $trxData['webhook_attempt_history'][] = [
                    'attempt' => $attemptCount,
                    'timestamp' => now()->toIso8601String(),
                    'status' => 'failed',
                    'reason' => 'missing_configuration',
                    'has_url' => ! empty($webhookConfig['url']),
                    'has_secret' => ! empty($webhookConfig['secret']),
                ];
                $transaction->update(['trx_data' => $trxData]);

                Log::channel('webhook')->warning('Webhook skipped: missing configuration', [
                    'trx_id' => $transaction->trx_id,
                    'trx_type' => $transaction->trx_type->value,
                    'attempt_count' => $attemptCount,
                ]);

                return false;
            }

            // Prepare generic webhook payload
            $payload = $this->buildGenericPayload($transaction, $message);

            // Send webhook using Spatie Webhook Server
            $this->dispatchWebhook(
                url: $webhookConfig['url'],
                secret: $webhookConfig['secret'],
                payload: $payload,
                transaction: $transaction
            );

            // Mark webhook as sent
            $transaction->setWebhookCall();

            // Update attempt tracking for success
            $trxData['webhook_attempts'] = $attemptCount;
            $trxData['webhook_attempt_history'][] = [
                'attempt' => $attemptCount,
                'timestamp' => now()->toIso8601String(),
                'status' => 'success',
                'webhook_url' => $webhookConfig['url'],
            ];
            $transaction->update(['trx_data' => $trxData]);

            Log::channel('webhook')->info('Generic webhook sent successfully', [
                'trx_id' => $transaction->trx_id,
                'trx_type' => $transaction->trx_type->value,
                'webhook_url' => $webhookConfig['url'],
                'attempt_count' => $attemptCount,
            ]);

            return true;
        } catch (\Exception $e) {
            // Get webhook config if available (might not be set if exception occurred earlier)
            $webhookConfig = isset($webhookConfig) ? $webhookConfig : $transaction->getWebhookConfig();

            // Update attempt tracking for failure
            $trxData['webhook_attempts'] = $attemptCount;
            $trxData['webhook_attempt_history'][] = [
                'attempt' => $attemptCount,
                'timestamp' => now()->toIso8601String(),
                'status' => 'failed',
                'reason' => 'exception',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ];
            $transaction->update(['trx_data' => $trxData]);

            Log::channel('webhook')->error('Generic webhook failed', [
                'trx_id' => $transaction->trx_id,
                'trx_type' => $transaction->trx_type->value,
                'attempt_count' => $attemptCount,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'webhook_url' => $webhookConfig['url'] ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Build payload for payment receive webhooks
     *
     * Constructs a comprehensive payload with payment details, customer information,
     * and transaction metadata for merchant webhook notifications.
     *
     * @param  Transaction  $transaction  The payment transaction
     * @param  string|null  $message  Optional custom message
     * @return array The webhook payload
     */
    protected function buildPaymentPayload(Transaction $transaction, string $rrn, ?string $message = null): array
    {
        $transaction->refresh();
        $trxData = is_array($transaction->trx_data) ? $transaction->trx_data : [];

        return [
            'event' => $transaction->trx_type->value,
            'data' => [
                'trx_id' => $transaction->trx_id,
                'trx_reference' => $transaction->trx_reference,
                'rrn' => $rrn,
                'amount' => $transaction->payable_amount,
                'currency_code' => $transaction->payable_currency,
                'description' => $transaction->description,
                'customer_name' => $transaction->customer?->name ?? $trxData['customer_name'] ?? null,
                'customer_email' => $transaction->customer?->email ?? $trxData['customer_email'] ?? null,
                'customer_phone' => $transaction->customer?->phone ?? $trxData['customer_phone'] ?? null,
                'merchant_id' => $transaction->merchant_id,
                'merchant_name' => $transaction->merchant?->business_name,
                'payment_method' => $transaction->provider,
            ],
            'message' => $message ?? $this->getDefaultMessage($transaction),
            'status' => $transaction->status->value,
            'timestamp' => $transaction->updated_at->unix(),
        ];
    }

    /**
     * Build payload for withdrawal webhooks
     *
     * Constructs a comprehensive payload with withdrawal details, account information,
     * and transaction metadata for merchant webhook notifications.
     *
     * @param  Transaction  $transaction  The withdrawal transaction
     * @param  string|null  $message  Optional custom message
     * @return array The webhook payload
     */
    protected function buildWithdrawalPayload(Transaction $transaction, ?string $message = null): array
    {
        $trxData = is_array($transaction->trx_data) ? $transaction->trx_data : [];
        $withdrawalAccount = $trxData['withdrawal_account'] ?? [];

        return [
            'event' => $transaction->trx_type->value,
            'data' => [
                'trx_id' => $transaction->trx_id,
                'trx_reference' => $transaction->trx_reference,
                'amount' => $transaction->amount,
                'net_amount' => $transaction->net_amount,
                'payable_amount' => $transaction->payable_amount,
                'currency_code' => $transaction->currency,
                'status' => $transaction->status->value,
                'description' => $transaction->description,
                'merchant_id' => $transaction->merchant_id,
                'merchant_name' => $transaction->merchant?->business_name,
                'withdrawal_method' => $transaction->provider,
                'account_name' => $withdrawalAccount['account_name'] ?? null,
                'account_holder_name' => $withdrawalAccount['account_holder_name'] ?? null,
                'account_bank_name' => $withdrawalAccount['account_bank_name'] ?? null,
                'account_number' => $withdrawalAccount['account_number'] ?? null,
                'account_bank_code' => $withdrawalAccount['account_bank_code'] ?? null,
                'trx_fee' => $transaction->trx_fee,
                'remarks' => $transaction->remarks,
                'environment' => config('app.mode'),
                'is_sandbox' => config('app.mode') === 'sandbox',
            ],
            'message' => $message ?? $this->getDefaultMessage($transaction),
            'timestamp' => $transaction->updated_at->timestamp,
        ];
    }

    /**
     * Build generic payload for other transaction types
     *
     * Constructs a basic payload with essential transaction information.
     *
     * @param  Transaction  $transaction  The transaction
     * @param  string|null  $message  Optional custom message
     * @return array The webhook payload
     */
    protected function buildGenericPayload(Transaction $transaction, ?string $message = null): array
    {
        return [
            'event' => $transaction->trx_type->value,
            'data' => [
                'trx_id' => $transaction->trx_id,
                'trx_reference' => $transaction->trx_reference,
                'trx_type' => $transaction->trx_type->value,
                'amount' => $transaction->amount,
                'net_amount' => $transaction->net_amount,
                'payable_amount' => $transaction->payable_amount,
                'currency_code' => $transaction->currency,
                'status' => $transaction->status->value,
                'description' => $transaction->description,
                'merchant_id' => $transaction->merchant_id,
                'merchant_name' => $transaction->merchant?->business_name,
                'provider' => $transaction->provider,
                'environment' => config('app.mode'),
                'is_sandbox' => config('app.mode') === 'sandbox',
            ],
            'message' => $message ?? $this->getDefaultMessage($transaction),
            'timestamp' => $transaction->updated_at->timestamp,
        ];
    }

    /**
     * Get default message based on transaction status
     *
     * Provides context-aware default messages based on transaction
     * type and status for webhook notifications.
     *
     * @param  Transaction  $transaction  The transaction
     * @return string The default message
     */
    protected function getDefaultMessage(Transaction $transaction): string
    {
        return match ($transaction->status) {
            TrxStatus::COMPLETED => match ($transaction->trx_type) {
                TrxType::RECEIVE_PAYMENT => 'Payment received successfully',
                TrxType::WITHDRAW => 'Withdrawal completed successfully',
                default => 'Transaction completed successfully',
            },
            TrxStatus::FAILED => match ($transaction->trx_type) {
                TrxType::RECEIVE_PAYMENT => 'Payment failed',
                TrxType::WITHDRAW => 'Withdrawal failed',
                default => 'Transaction failed',
            },
            TrxStatus::CANCELED => match ($transaction->trx_type) {
                TrxType::RECEIVE_PAYMENT => 'Payment canceled',
                TrxType::WITHDRAW => 'Withdrawal canceled',
                default => 'Transaction canceled',
            },
            TrxStatus::REFUNDED => match ($transaction->trx_type) {
                TrxType::RECEIVE_PAYMENT => 'Payment refunded',
                TrxType::WITHDRAW => 'Withdrawal refunded',
                default => 'Transaction refunded',
            },
            TrxStatus::AWAITING_FI_PROCESS => 'Transaction awaiting financial institution process',
            TrxStatus::AWAITING_PG_PROCESS => 'Transaction awaiting payment gateway process',
            TrxStatus::AWAITING_USER_ACTION => 'Transaction awaiting user action',
            TrxStatus::AWAITING_ADMIN_APPROVAL => 'Transaction awaiting admin approval',
            default => 'Transaction status updated',
        };
    }

    /**
     * Dispatch webhook using Spatie Webhook Server
     *
     * Handles the actual webhook HTTP request with proper signature
     * generation and error handling.
     *
     * @param  string  $url  The webhook URL
     * @param  string  $secret  The webhook secret for signing
     * @param  array  $payload  The webhook payload
     * @param  Transaction  $transaction  The transaction for logging
     *
     * @throws \Exception When webhook dispatch fails
     */
    protected function dispatchWebhook(string $url, string $secret, array $payload, Transaction $transaction): void
    {
        if (app()->environment('local')) {
            $url = config('site.webhook_url');
        } else {
            $url = $url;
        }
        //        dd($url, $payload, $transaction);
        try {
            WebhookCall::create()
                ->url($url)
                ->payload($payload)
                ->useSecret($secret)
                ->dispatch();

            Log::channel('webhook')->info('Webhook dispatched', [
                'trx_id' => $transaction->trx_id,
                'url' => $url,
                'event' => $payload['event'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            Log::channel('webhook')->error('Webhook dispatch failed', [
                'trx_id' => $transaction->trx_id,
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Send webhook synchronously (for testing purposes)
     *
     * Sends a webhook immediately and returns the response.
     * Useful for testing webhook endpoints.
     *
     * @param  Transaction  $transaction  The transaction
     * @param  string|null  $message  Optional custom message
     * @return array Returns response data including success status and response details
     *
     * @throws GuzzleException
     */
    public function sendWebhookSync(Transaction $transaction, ?string $message = null): array
    {
        if (! $transaction->isWebhookEnabled()) {
            return [
                'success' => false,
                'message' => 'Webhooks not enabled for this transaction',
            ];
        }

        $webhookConfig = $transaction->getWebhookConfig();

        if (empty($webhookConfig['url']) || empty($webhookConfig['secret'])) {
            return [
                'success' => false,
                'message' => 'Webhook configuration incomplete',
            ];
        }

        // Build appropriate payload based on transaction type
        $payload = match ($transaction->trx_type) {
            TrxType::RECEIVE_PAYMENT => $this->buildPaymentPayload($transaction, $message),
            TrxType::WITHDRAW => $this->buildWithdrawalPayload($transaction, $message),
            default => $this->buildGenericPayload($transaction, $message),
        };

        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 10,
                'verify' => $webhookConfig['verify_ssl'] ?? true,
                'http_errors' => false,
            ]);

            $signature = hash_hmac('sha256', json_encode($payload), $webhookConfig['secret']);

            $response = $client->post($webhookConfig['url'], [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Signature' => $signature,
                    'User-Agent' => 'Webhook/1.0',
                ],
                'body' => json_encode($payload),
            ]);

            return [
                'success' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getBody()->getContents(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
