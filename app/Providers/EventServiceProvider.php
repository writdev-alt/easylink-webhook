<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\WebhookReceived::class => [
            \App\Listeners\StoreWebhookCallListener::class,
        ],
        \Spatie\WebhookServer\Events\WebhookCallFailedEvent::class => [
            \App\Listeners\LogWebhookCallFailedListener::class,
        ],
        \Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent::class => [
            \App\Listeners\LogFinalWebhookCallFailedListener::class,
        ],
        \Spatie\WebhookServer\Events\WebhookCallSucceededEvent::class => [
            \App\Listeners\LogWebhookCallSucceededListener::class,
        ],
    ];
}
