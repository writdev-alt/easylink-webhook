<?php

namespace App\Listeners;

use App\Listeners\Concerns\LogsWebhookCalls;
use Spatie\WebhookServer\Events\WebhookCallEvent;

class LogWebhookCallFailedListener
{
    use LogsWebhookCalls;

    /**
     * Check if this event should be processed.
     * Skip logging if this is the final attempt, as LogFinalWebhookCallFailedListener will handle it.
     */
    protected function shouldProcess(WebhookCallEvent $event): bool
    {
        $maxTries = config('webhook-server.tries', 3);

        return $event->attempt < $maxTries;
    }

    /**
     * Get the log level for the webhook event.
     */
    protected function getLogLevel(): string
    {
        return 'emergency';
    }

    /**
     * Get the log message for the webhook event.
     */
    protected function getLogMessage(): string
    {
        return 'Webhook call failed';
    }

    /**
     * Get the status for the webhook log entry.
     */
    protected function getWebhookStatus(): string
    {
        return 'pending';
    }
}
