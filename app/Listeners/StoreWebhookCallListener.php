<?php

namespace App\Listeners;

use App\Events\WebhookReceived;
use App\Models\WebhookCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class StoreWebhookCallListener implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function handle(WebhookReceived $event): void
    {

        $webhookCall = WebhookCall::create([
            'name' => $event->gateway,
            'url' => $event->url,
            'headers' => $event->headers,
            'payload' => $event->payload,
            'http_verb' => $event->httpVerb,
        ]);

        Log::info('Webhook saved to database', [
            'gateway' => $event->gateway,
            'action' => $event->action,
            'webhook_call_id' => $webhookCall->id,
        ]);
    }
}
