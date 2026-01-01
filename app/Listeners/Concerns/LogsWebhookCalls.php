<?php

namespace App\Listeners\Concerns;

use App\Models\TransactionWebhookLog;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookServer\Events\WebhookCallEvent;
use Wrpay\Core\Models\Transaction;

trait LogsWebhookCalls
{
    /**
     * Get the log level for the webhook event.
     */
    abstract protected function getLogLevel(): string;

    /**
     * Get the log message for the webhook event.
     */
    abstract protected function getLogMessage(): string;

    /**
     * Get the status for the webhook log entry.
     */
    abstract protected function getWebhookStatus(): string;

    /**
     * Check if this event should be processed.
     */
    protected function shouldProcess(WebhookCallEvent $event): bool
    {
        return true;
    }

    /**
     * Handle the webhook call event.
     */
    public function handle(WebhookCallEvent $event): void
    {
        if (! $this->shouldProcess($event)) {
            return;
        }

        $this->logWebhookEvent($event);
        $this->saveWebhookLog($event);
    }

    /**
     * Log the webhook event to the webhook channel.
     */
    protected function logWebhookEvent(WebhookCallEvent $event): void
    {
        $responseBody = $this->getResponseBody($event);

        Log::channel('webhook')->{$this->getLogLevel()}($this->getLogMessage(), [
            'http_verb' => $event->httpVerb,
            'webhook_url' => $event->webhookUrl,
            'payload' => $event->payload,
            'headers' => $event->headers,
            'meta' => $event->meta,
            'tags' => $event->tags,
            'attempt' => $event->attempt,
            'response' => $responseBody,
            'http_status' => $this->getHttpStatus($event),
            'response_body' => $responseBody,
            'error_type' => $event->errorType ?? null,
            'error_message' => $event->errorMessage ?? null,
            'uuid' => $event->uuid ?? null,
            'transfer_stats' => $this->getTransferStats($event),
        ]);
    }

    /**
     * Save the webhook log to the database.
     */
    protected function saveWebhookLog(WebhookCallEvent $event): void
    {
        $transaction = $this->getTransaction($event);

        if (! $transaction) {
            return;
        }

        try {
            TransactionWebhookLog::create($this->buildLogData($event, $transaction));
        } catch (\Throwable $e) {
            Log::error('Error logging webhook call', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'trx_id' => $event->payload['data']['trx_id'] ?? null,
            ]);
        }
    }

    /**
     * Get the transaction for the webhook event.
     */
    protected function getTransaction(WebhookCallEvent $event): ?Transaction
    {
        $trxId = $event->payload['data']['trx_id'] ?? null;

        if (! $trxId) {
            return null;
        }

        return Transaction::where('trx_id', $trxId)->first();
    }

    /**
     * Build the log data array for database insertion.
     */
    protected function buildLogData(WebhookCallEvent $event, Transaction $transaction): array
    {
        $payload = $event->payload ?? [];

        return [
            'trx_id' => $payload['data']['trx_id'] ?? '',
            'webhook_name' => $transaction->trx_type->value.'.'.$transaction->status->value,
            'webhook_url' => $event->webhookUrl ?? '',
            'event_type' => $payload['event'] ?? 'webhook',
            'attempt' => $event->attempt ?? 1,
            'status' => $this->getWebhookStatus(),
            'http_status' => $this->getHttpStatus($event),
            'response_body' => $this->getResponseBody($event),
            'error_message' => $event->errorMessage ?? null,
            'request_payload' => $payload ?: new \stdClass,
            'sent_at' => now(),
            'created_at' => now(),
        ];
    }

    /**
     * Get the HTTP status code from the response.
     */
    protected function getHttpStatus(WebhookCallEvent $event): ?int
    {
        if (! $event->response || ! method_exists($event->response, 'getStatusCode')) {
            return null;
        }

        return $event->response->getStatusCode();
    }

    /**
     * Get the response body from the response.
     */
    protected function getResponseBody(WebhookCallEvent $event): ?string
    {
        if (! $event->response || ! method_exists($event->response, 'getBody')) {
            return null;
        }

        return (string) $event->response->getBody();
    }

    /**
     * Get transfer statistics from the event.
     */
    protected function getTransferStats(WebhookCallEvent $event): ?array
    {
        if (! $event->transferStats) {
            return null;
        }

        return [
            'total_time' => $event->transferStats->getTransferTime(),
            'namelookup_time' => $event->transferStats->getHandlerStat('namelookup_time'),
            'connect_time' => $event->transferStats->getHandlerStat('connect_time'),
        ];
    }
}
