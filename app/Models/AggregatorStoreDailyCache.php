<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aggregator Store Daily Cache Model
 * 
 * Stores daily aggregated transaction statistics for aggregator stores.
 *
 * @property \Carbon\Carbon $date
 * @property string $merchant_id
 * @property int|null $merchant_aggregator_id
 * @property float $total_payable_amount
 * @property int $total_transactions_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStoreDailyCache newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStoreDailyCache newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStoreDailyCache query()
 * @mixin \Eloquent
 */
class AggregatorStoreDailyCache extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'aggregator_store_daily_cache';

    /**
     * The primary key for the model.
     *
     * @var array<int, string>
     */
    protected $primaryKey = ['date', 'merchant_id'];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'date',
        'merchant_id',
        'merchant_aggregator_id',
        'total_payable_amount',
        'total_transactions_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'merchant_id' => 'string',
        'merchant_aggregator_id' => 'integer',
        'total_payable_amount' => 'decimal:2',
        'total_transactions_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Support composite keys when saving/updating (e.g., updateOrCreate).
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();

        if (! is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $value = $this->getOriginal($keyName, $this->getAttribute($keyName));
            $query->where($keyName, '=', $value);
        }

        return $query;
    }
}
