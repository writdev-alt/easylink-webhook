<?php

namespace App\Jobs;

use App\Models\WebhookCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StoreWebhookPayloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $gateway,
        public array $payload,
        public string $trxId,
        public string $requestUrl,
        public array $requestHeaders,
        public string $requestMethod,
    ) {}

    public function handle(): void
    {
        try {
            // Create webhook call record
            $webhookCall = WebhookCall::create([
                'name' => $this->gateway,
                'url' => $this->requestUrl,
                'headers' => $this->requestHeaders,
                'http_verb' => $this->requestMethod,
                'trx_id' => $this->trxId,
            ]);

            // Save payload to storage using model method
            $saved = $webhookCall->savePayload($this->payload);

            if ($saved) {
                Log::info('Webhook payload saved successfully', [
                    'gateway' => $this->gateway,
                    'trx_id' => $this->trxId,
                    'webhook_call_id' => $webhookCall->id,
                    'path' => $webhookCall->path,
                ]);
            } else {
                Log::warning('Webhook payload save returned false', [
                    'gateway' => $this->gateway,
                    'trx_id' => $this->trxId,
                    'webhook_call_id' => $webhookCall->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while storing webhook payload', [
                'gateway' => $this->gateway,
                'trx_id' => $this->trxId,
                'error' => $e->getMessage(),
                'error_class' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't re-throw to prevent job failure - log and continue
            // Uncomment the line below if you want jobs to fail on storage errors
            // throw $e;
        }
    }
}
