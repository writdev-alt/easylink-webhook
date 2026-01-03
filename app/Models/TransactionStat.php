<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Wrpay\Core\Models\Merchant;

// For UUIDs

/**
 * @property string $id
 * @property string $model_type
 * @property int $model_id
 * @property int $total_transactions
 * @property int $total_amount
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $model
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat whereTotalTransactions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionStat whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TransactionStat extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    protected $connection;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'model_id',
        'model_type',
        'type',
        'total_transactions',
        'total_amount',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'model_id' => 'int',
        'total_transactions' => 'int',
        'total_amount' => 'int',
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

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(config('wrpay-core.models.merchant', Merchant::class), 'model_id')
            ->where('model_type', config('wrpay-core.models.merchant', Merchant::class),);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'model_id')->where('model_type', User::class);
    }
}
