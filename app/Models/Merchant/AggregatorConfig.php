<?php

namespace App\Models\Merchant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sqits\UserStamps\Concerns\HasUserStamps;

/**
 * @property int $id
 * @property int $merchant_aggregator_id
 * @property string $config_key
 * @property array<array-key, mixed> $config_sandbox
 * @property array<array-key, mixed> $config_production
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Merchant\Aggregator $aggregator
 * @property-read array $config_value
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereConfigKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereConfigProduction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereConfigSandbox($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereMerchantAggregatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereUpdatedAt($value)
 *
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereUpdatedBy($value)
 *
 * @property string|null $uuid
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorConfig whereUuid($value)
 *
 * @mixin \Eloquent
 */
class AggregatorConfig extends Model
{
    use HasFactory;
    //    use HasUserStamps;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'merchant_aggregator_configs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'merchant_aggregator_id',
        'config_key',
        'config_sandbox',
        'config_production',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'config_sandbox' => 'array',
        'config_production' => 'array',
    ];

    /**
     * Get the aggregator that owns the config.
     */
    public function aggregator(): BelongsTo
    {
        return $this->belongsTo(Aggregator::class, 'merchant_aggregator_id');
    }

    /**
     * Get the config value based on the current app mode.
     */
    public function getConfigValueAttribute(): array
    {
        return $this->{'config_'.config('app_mode')};
    }
}
