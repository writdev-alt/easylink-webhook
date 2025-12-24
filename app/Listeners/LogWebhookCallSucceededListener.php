<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

class LogWebhookCallSucceededListener
{
    /**
     * Handle the event.
     */
    public function handle(WebhookCallSucceededEvent $event): void
    {
        try {
            $webhookCall = $event->webhookCall ?? null;
            $response = $event->response ?? null;

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

            if ($response) {
                $logData['response'] = [
                    'status_code' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                    'body' => method_exists($response, 'getBody') ? (string) $response->getBody() : null,
                ];
            }
            activity()
//                ->performedOn($webhookCall)
                ->withProperties($logData)
                ->log('Webhook call succeeded');

            Log::info('Webhook call succeeded', $logData);
        } catch (\Throwable $e) {
            Log::error('Error logging webhook call success', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
        }
    }
}
