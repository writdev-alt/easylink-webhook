<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [

        \Spatie\WebhookServer\Events\WebhookCallFailedEvent::class => [
            \App\Listeners\LogWebhookCallFailedListener::class,
        ],
        \Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent::class => [
            \App\Listeners\LogFinalWebhookCallFailedListener::class,
        ],
        \Spatie\WebhookServer\Events\WebhookCallSucceededEvent::class => [
            \App\Listeners\LogWebhookCallSucceededListener::class,
        ],
        \Illuminate\Http\Client\Events\RequestSending::class => [
            \App\Listeners\LogHttpResponse::class,
        ],

        \Illuminate\Http\Client\Events\ResponseReceived::class => [
            \App\Listeners\LogHttpResponse::class,
        ],
    ];
}
