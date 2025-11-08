<?php

namespace App\Jobs;

use App\Models\WebhookCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

    public function handle()
    {
        WebhookCall::create([
            'name' => $this->gateway,
            'url' => $this->requestUrl,
            'headers' => $this->requestHeaders,
            'payload' => $this->payload,
            'http_verb' => $this->requestMethod,
            'trx_id' => $this->trxId,
        ]);
    }
}
