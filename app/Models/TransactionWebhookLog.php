<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model; // For UUIDs

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionWebhookLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionWebhookLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionWebhookLog query()
 * @mixin \Eloquent
 */
class TransactionWebhookLog extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    protected $connection;

    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'transaction_webhook_logs';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'trx_id',
        'webhook_name',
        'webhook_url',
        'event_type',
        'attempt',
        'status',
        'http_status',
        'response_body',
        'error_message',
        'request_payload',
        'sent_at',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'attempt' => 'int',
        'http_status' => 'int',
        'request_payload' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
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
