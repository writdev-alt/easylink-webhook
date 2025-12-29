<?php

namespace App\Jobs;

use App\Models\WebhookCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
            // 'payload' => $this->payload,
            'http_verb' => $this->requestMethod,
            'trx_id' => $this->trxId,
        ]);
        // save request to gcs
        try {
            $filePath = 'webhook-payload/'.$this->gateway.'/'.$this->trxId.'.json';
            $content = json_encode($this->payload, JSON_PRETTY_PRINT);

            // Check if GCS disk is configured
            $gcsConfig = config('filesystems.disks.gcs');
            if (! $gcsConfig) {
                Log::error('GCS disk not configured', [
                    'gateway' => $this->gateway,
                    'trx_id' => $this->trxId,
                ]);

                return;
            }

            // Log configuration (without sensitive data)
            Log::debug('GCS configuration check', [
                'bucket' => $gcsConfig['bucket'] ?? 'not set',
                'project_id' => $gcsConfig['project_id'] ?? 'not set',
                'key_file_path' => $gcsConfig['key_file_path'] ? 'set' : 'not set',
                'driver' => $gcsConfig['driver'] ?? 'not set',
            ]);

            $disk = Storage::disk('gcs');

            // Verify disk exists
            if (! $disk) {
                Log::error('GCS disk not available', [
                    'gateway' => $this->gateway,
                    'trx_id' => $this->trxId,
                ]);

                return;
            }

            $result = $disk->put($filePath, $content);

            if ($result) {
                Log::info('Webhook payload saved to GCS', [
                    'gateway' => $this->gateway,
                    'trx_id' => $this->trxId,
                    'file_path' => $filePath,
                    'bucket' => $gcsConfig['bucket'] ?? null,
                ]);
            } else {
                Log::error('Failed to save webhook payload to GCS - put() returned false', [
                    'gateway' => $this->gateway,
                    'trx_id' => $this->trxId,
                    'file_path' => $filePath,
                    'bucket' => $gcsConfig['bucket'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while saving webhook payload to GCS', [
                'gateway' => $this->gateway,
                'trx_id' => $this->trxId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't re-throw to prevent job failure - log and continue
            // Uncomment the line below if you want jobs to fail on GCS errors
            // throw $e;
        }
    }
}
