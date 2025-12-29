<?php

namespace App\Listeners;

use App\Listeners\Concerns\LogsWebhookCalls;

class LogWebhookCallSucceededListener
{
    use LogsWebhookCalls;

    /**
     * Get the log level for the webhook event.
     */
    protected function getLogLevel(): string
    {
        return 'info';
    }

    /**
     * Get the log message for the webhook event.
     */
    protected function getLogMessage(): string
    {
        return 'Webhook call succeeded';
    }

    /**
     * Get the status for the webhook log entry.
     */
    protected function getWebhookStatus(): string
    {
        return 'success';
    }
}
