<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo; // For UUIDs

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

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'model_id')->where('model_type', Merchant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'model_id')->where('model_type', User::class);
    }
}
