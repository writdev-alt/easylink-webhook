<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon $date
 * @property int $user_id
 * @property numeric $total_incoming
 * @property int $count_incoming
 * @property numeric $total_withdraw
 * @property int $count_withdraw
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary whereCountIncoming($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary whereCountWithdraw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary whereTotalIncoming($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary whereTotalWithdraw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyTransactionSummary whereUserId($value)
 * @mixin \Eloquent
 */
class DailyTransactionSummary extends Model
{
    protected $connection;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'daily_transaction_summary';

    /**
     * The primary key for the model.
     *
     * @var array<int, string>
     */
    protected $primaryKey = ['date', 'user_id'];

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
        'user_id',
        'total_incoming',
        'count_incoming',
        'total_withdraw',
        'count_withdraw',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'user_id' => 'integer',
        'total_incoming' => 'decimal:2',
        'count_incoming' => 'integer',
        'total_withdraw' => 'decimal:2',
        'count_withdraw' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns this daily transaction summary.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
