<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
}
