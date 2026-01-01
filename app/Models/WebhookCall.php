<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $url
 * @property array<array-key, mixed>|null $headers
 * @property array<array-key, mixed>|null $payload
 * @property string $http_verb
 * @property string|null $exception
 * @property string|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $trx_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereException($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereHeaders($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereHttpVerb($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereTrxId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereUrl($value)
 * @mixin \Eloquent
 */
class WebhookCall extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    protected $connection;

    protected $fillable = [
        'name',
        'url',
        'http_verb',
        'headers',
        'payload',
        'exception',
        'trx_id',
        'hash',
        'path',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection($this->resolveConfiguredConnection());
    }

    protected function resolveConfiguredConnection(): string
    {
        return (string) config('database.webhook_calls_connection', 'mysql_site');
    }

    /**
     * Get the storage path for storing the payload.
     */
    public function getPayloadPath(): string
    {
        // Use trx_id if available, otherwise use webhook call ID
        $identifier = $this->trx_id ?? $this->id;
        $filename = sprintf('%s.json', $identifier);

        return "webhook-payload/{$this->name}/{$filename}";
    }

    /**
     * Save payload array to default storage.
     *
     * @param array|null $payload Optional payload array. If null, uses model's payload attribute.
     * @return bool True if saved successfully, false otherwise.
     */
    public function savePayload(?array $payload = null): bool
    {
        $payload = $payload ?? $this->payload;

        // Only save if payload exists and is not empty
        if (empty($payload)) {
            return false;
        }

        // Skip if already saved (check if file exists in storage)
        $path = $this->path ?? $this->getPayloadPath();
        
        if (!empty($this->path) && Storage::disk()->exists($path)) {
            return true;
        }

        try {
            // Convert payload to JSON string
            $payloadJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Save to default storage
            $saved = Storage::disk()->put($path, $payloadJson);

            if (!$saved) {
                \Log::error('Failed to save webhook payload to storage - put() returned false', [
                    'webhook_call_id' => $this->id,
                    'path' => $path,
                ]);
                return false;
            }

            // Mark with hash and path if not already set
            if (empty($this->hash) || empty($this->path)) {
                $this->hash = hash('sha256', $payloadJson);
                $this->path = $path;
                $this->saveQuietly(); // Use saveQuietly to avoid triggering events again
            }

            return true;
        } catch (\Exception $e) {
            // Log error but don't fail the save operation
            \Log::error('Failed to save webhook payload to storage', [
                'webhook_call_id' => $this->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get payload from default storage if it exists.
     *
     * @return array|null The payload from storage, or null if not found
     */
    public function getPayloadFromStorage(): ?array
    {
        $path = $this->path ?? $this->getPayloadPath();

        if (! Storage::disk()->exists($path)) {
            return null;
        }

        try {
            $payloadJson = Storage::disk()->get($path);
            $payload = json_decode($payloadJson, true);

            return $payload;
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve webhook payload from storage', [
                'webhook_call_id' => $this->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the storage URL for the payload file.
     *
     * @return string|null The public URL to the payload file, or null if not found
     */
    public function getPayloadUrl(): ?string
    {
        $path = $this->path ?? $this->getPayloadPath();

        if (! Storage::disk()->exists($path)) {
            return null;
        }

        try {
            return Storage::disk()->url($path);
        } catch (\Exception $e) {
            \Log::error('Failed to get storage URL for webhook payload', [
                'webhook_call_id' => $this->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @deprecated Use savePayload() instead
     */
    public function savePayloadToGcs(): bool
    {
        return $this->savePayload();
    }

    /**
     * @deprecated Use getPayloadPath() instead
     */
    public function getGcsPayloadPath(): string
    {
        return $this->getPayloadPath();
    }

    /**
     * @deprecated Use getPayloadFromStorage() instead
     */
    public function getPayloadFromGcs(): ?array
    {
        return $this->getPayloadFromStorage();
    }

    /**
     * @deprecated Use getPayloadUrl() instead
     */
    public function getPayloadGcsUrl(): ?string
    {
        return $this->getPayloadUrl();
    }
}
