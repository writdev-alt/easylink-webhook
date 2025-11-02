<?php

namespace App\Listeners;

use App\Events\WebhookReceived;
use App\Models\WebhookCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StoreWebhookCallListener implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function handle(WebhookReceived $event): void
    {
        $webhookCall = WebhookCall::create([
            'uuid' => (string) Str::uuid(),
            'name' => $event->gateway,
            'url' => $event->url,
            'headers' => $event->headers,
            'payload' => $event->payload,
            'http_verb' => $event->httpVerb,
            'raw_body' => $event->rawBody,
            'meta' => array_filter([
                'action' => $event->action,
                'query' => $event->query,
            ], fn ($value) => $value !== null && $value !== []),
        ]);

        Log::info('Webhook saved to database', [
            'gateway' => $event->gateway,
            'action' => $event->action,
            'webhook_call_id' => $webhookCall->id,
        ]);
    }
}
