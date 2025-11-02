<?php

namespace App\Providers;

use App\Events\WebhookReceived;
use App\Listeners\StoreWebhookCallListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        WebhookReceived::class => [
            StoreWebhookCallListener::class,
        ],
    ];
}
