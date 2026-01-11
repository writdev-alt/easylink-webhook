<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogHttpResponse implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'webhook-logs';

    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof RequestSending) {
            $this->logRequest($event);
        }

        if ($event instanceof ResponseReceived) {
            $this->logResponse($event);
        }
    }

    protected function logRequest(RequestSending $event): void
    {
        try {
            $request = $event->request;

            Log::info('Outgoing HTTP Request Sent', [
                'method' => $request->method(),
                'url' => $request->url(),
                'headers' => $this->serializeHeaders($request->headers()),
                'body' => $this->serializeBody($request->data()),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log HTTP request', [
                'error' => $e->getMessage(),
                'url' => $event->request->url() ?? 'unknown',
            ]);
        }
    }

    protected function logResponse(ResponseReceived $event): void
    {
        try {
            $request = $event->request;
            $response = $event->response;

            Log::info('Outgoing HTTP Response Received', [
                'method' => $request->method(),
                'url' => $request->url(),
                'status' => $response->status(),
                'response_body' => $this->serializeBody($response),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log HTTP response', [
                'error' => $e->getMessage(),
                'url' => $event->request->url() ?? 'unknown',
            ]);
        }
    }

    /**
     * Serialize headers to array format.
     */
    protected function serializeHeaders($headers): array
    {
        if (is_array($headers)) {
            return $headers;
        }

        if (method_exists($headers, 'toArray')) {
            return $headers->toArray();
        }

        // Convert to array if it's a collection or other iterable
        try {
            return is_iterable($headers) ? iterator_to_array($headers) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Serialize body content safely.
     */
    protected function serializeBody($body): string|array|null
    {
        if (is_null($body)) {
            return null;
        }

        // Handle stream resources
        if (is_resource($body)) {
            try {
                // Check if it's a stream resource
                $meta = stream_get_meta_data($body);
                if (isset($meta['stream_type'])) {
                    // Check if stream is seekable
                    $seekable = $meta['seekable'] ?? false;
                    $position = null;

                    if ($seekable) {
                        // Save current position
                        $position = ftell($body);
                        // Only rewind if we got a valid position
                        if ($position !== false && is_int($position)) {
                            rewind($body);
                        } else {
                            // If ftell failed, don't try to seek
                            $seekable = false;
                            $position = null;
                        }
                    }

                    // Read the content (limit to 10000 bytes to prevent log bloat)
                    $content = stream_get_contents($body, 10000);

                    // Restore position if stream is seekable and we have a valid position
                    if ($seekable && $position !== null && $position !== false && is_int($position) && is_resource($body)) {
                        try {
                            fseek($body, $position);
                        } catch (\Throwable $e) {
                            // Ignore errors when restoring position
                        }
                    }

                    // Add truncation indicator if we hit the limit
                    $isTruncated = strlen($content) >= 10000;

                    return $isTruncated ? $content.'... (truncated)' : $content;
                }
            } catch (\Throwable $e) {
                return '[Unable to read stream: '.$e->getMessage().']';
            }
        }

        if (is_string($body)) {
            // Limit body size to prevent log bloat
            return strlen($body) > 10000 ? substr($body, 0, 10000).'... (truncated)' : $body;
        }

        if (is_array($body) || is_object($body)) {
            try {
                $serialized = is_array($body) ? $body : json_decode(json_encode($body), true);

                return is_array($serialized) ? $serialized : (string) $body;
            } catch (\Throwable $e) {
                return '[Unable to serialize body]';
            }
        }

        return (string) $body;
    }
}
