<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;

class LogWebhookCallFailedListener
{
    /**
     * Handle the event.
     */
    public function handle(WebhookCallFailedEvent $event): void
    {
        try {
            $webhookCall = $event->webhookCall ?? null;
            $exception = $event->exception ?? null;

            $logData = [
                'timestamp' => now()->toIso8601String(),
            ];

            if ($webhookCall) {
                $logData['url'] = $webhookCall->url ?? null;
                $logData['attempt'] = $webhookCall->attempt ?? null;
                $logData['uuid'] = $webhookCall->uuid ?? null;
                $logData['payload'] = $webhookCall->payload ?? null;
                $logData['headers'] = $webhookCall->headers ?? null;
            }

            if ($exception) {
                $logData['exception'] = [
                    'message' => $exception->getMessage(),
                    'class' => get_class($exception),
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            Log::error('Webhook call failed', $logData);
        } catch (\Throwable $e) {
            Log::error('Error logging webhook call failure', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
        }
    }
}
