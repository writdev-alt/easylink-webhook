<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $gateway,
        public readonly ?string $action,
        public readonly string $url,
        public readonly array $headers,
        public readonly array $payload,
        public readonly string $httpVerb,
        public readonly array $query,
        public readonly ?string $rawBody = null,
    ) {}
}
