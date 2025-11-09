<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookCall extends Model
{
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
