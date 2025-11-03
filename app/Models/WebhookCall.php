<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static static create(array $attributes = [])
 *
 * @property int $id
 * @property string|null $uuid
 * @property string $name
 * @property string $url
 * @property string $http_verb
 * @property array<array-key, mixed>|null $headers
 * @property array<array-key, mixed>|null $payload
 * @property string|null $raw_body
 * @property array<array-key, mixed>|null $meta
 * @property string|null $exception
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereException($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereHeaders($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereHttpVerb($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereMeta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereRawBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookCall whereUuid($value)
 *
 * @mixin \Eloquent
 */
class WebhookCall extends Model
{
    protected $connection = 'mysql_site';

    protected $fillable = [
        'uuid',
        'name',
        'url',
        'http_verb',
        'headers',
        'payload',
        'raw_body',
        'meta',
        'exception',
    ];

    protected $casts = [
        'id' => 'int',
        'headers' => 'array',
        'payload' => 'array',
        'meta' => 'array',
    ];
}
