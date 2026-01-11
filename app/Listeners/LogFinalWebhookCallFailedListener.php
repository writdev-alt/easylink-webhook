<?php

namespace App\Listeners;

use App\Listeners\Concerns\LogsWebhookCalls;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogFinalWebhookCallFailedListener implements ShouldQueue
{
    use InteractsWithQueue, LogsWebhookCalls;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'webhook-logs';

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
        return 'failed';
    }
}
