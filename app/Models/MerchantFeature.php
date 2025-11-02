<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property int $merchant_id
 * @property string $feature
 * @property string|null $description
 * @property string $type
 * @property string $category
 * @property bool $status
 * @property array<array-key, mixed>|null $value
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read mixed $dynamic_status
 * @property-read mixed $effective_value
 * @property-read \App\Models\Merchant $merchant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature byCategory(string $category)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature enabled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature forMerchant(int $merchantId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereFeature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantFeature whereValue($value)
 *
 * @mixin \Eloquent
 */
class MerchantFeature extends Model
{
    protected $fillable = [
        'merchant_id',
        'feature',
        'description',
        'status',
        'value',
        'sort_order',
        'category',
        'type',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'status' => 'boolean',
        'sort_order' => 'integer',
        'value' => 'array', // Cast JSON column to array
    ];

    /**
     * Accessor: Dynamically return the feature status or value based on merchant settings
     */
    public function getDynamicStatusAttribute()
    {
        $merchant = $this->merchant;

        if (! $merchant) {
            return $this->status;
        }

        // Override status based on merchant verification/approval status
        if ($this->feature === 'webhooks_enabled' && $merchant->status !== 'approved') {
            return false; // Cannot enable webhooks for non-approved merchants
        }

        if ($this->feature === 'api_ip_whitelist_enabled' && $merchant->status !== 'approved') {
            return false; // Cannot enable IP whitelist for non-approved merchants
        }

        if ($this->feature === 'multi_currency_enabled' && $merchant->status !== 'approved') {
            return false; // Cannot enable multi-currency for non-approved merchants
        }

        return $this->status;
    }

    /**
     * Get the effective value of the feature (either boolean status or custom value)
     */
    public function getEffectiveValueAttribute()
    {
        if ($this->type === 'boolean') {
            return $this->status; // For boolean types, use status directly
        }

        return $this->value ?? $this->status;
    }

    /**
     * Get validation rules for this feature
     */
    public function getValidationRules(): array
    {
        $configFeatures = config('merchantFeatures.features');
        $additionalFields = config('merchantFeatures.additional_fields');

        // Find feature in config
        $featureConfig = collect($configFeatures)->firstWhere('feature', $this->feature);

        if ($featureConfig && isset($featureConfig['validation'])) {
            return [$this->feature => $featureConfig['validation']];
        }

        // Check additional fields
        if (isset($additionalFields[$this->feature])) {
            $fieldConfig = $additionalFields[$this->feature];

            return [$this->feature => $fieldConfig['validation'] ?? ''];
        }

        return [$this->feature => $this->getDefaultValidationRule()];
    }

    /**
     * Get default validation rule based on type
     */
    private function getDefaultValidationRule(): string
    {
        return match ($this->type) {
            'boolean' => 'boolean',
            'integer' => 'integer',
            'string' => 'string|max:255',
            'email' => 'email|max:255',
            'url' => 'url|max:500',
            'array' => 'array',
            'textarea' => 'string|max:5000',
            default => 'string',
        };
    }

    /**
     * Scope to get features for a specific merchant
     */
    public function scopeForMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId)->orderBy('sort_order');
    }

    /**
     * Scope to get features by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get enabled features
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', true);
    }

    /**
     * Check if a feature is enabled/active
     */
    public function isEnabled(): bool
    {
        return (bool) $this->dynamic_status;
    }

    /**
     * Get feature value as specific type
     */
    public function getValueAsType(): mixed
    {
        // For boolean and integer types, use the effective value
        if ($this->type === 'boolean') {
            return (bool) $this->effective_value;
        }

        if ($this->type === 'integer') {
            return (int) $this->effective_value;
        }

        // For array type, return the value as-is (Laravel handles JSON decoding)
        if ($this->type === 'array') {
            return $this->value ?? [];
        }

        // For other types, return the value directly
        return $this->value ?? $this->effective_value;
    }

    /**
     * Set value with type-specific formatting
     */
    public function setTypedValue(mixed $value): void
    {
        // Format value for JSON column
        $formattedValue = self::formatValueForJsonColumn($value, $this->type);

        // Set the value (Laravel will automatically handle JSON encoding)
        $this->value = $formattedValue;

        // Update status based on type - ensure status is never null
        if ($this->type === 'boolean') {
            $this->status = (bool) $value;
        } elseif ($this->type === 'integer') {
            // For integer types, status indicates if the feature is enabled (non-zero value)
            $this->status = ! empty($value) && $value !== 0;
        } else {
            // For other types (string, email, url, textarea, array), status indicates if feature has a value
            $this->status = ! empty($value) && $value !== null;
        }
    }

    /**
     * Format value for JSON column based on type
     */
    public static function formatValueForJsonColumn(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'string', 'email', 'url', 'textarea' => (string) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }

    /**
     * Relationship: MerchantFeature belongs to a Merchant
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
