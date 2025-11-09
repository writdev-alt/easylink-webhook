<?php

namespace App\Models;

use App\Enums\MerchantStatus;
use App\Enums\TrxStatus;
use App\Enums\TrxType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property int $user_id
 * @property int|null $agent_id
 * @property string $merchant_key
 * @property string $business_name
 * @property string|null $site_url
 * @property int $currency_id
 * @property string|null $business_logo
 * @property string|null $business_email
 * @property string|null $business_description
 * @property string|null $business_whatsapp_group_id
 * @property string|null $business_telegram_group_id
 * @property float $ma_fee Merchant Aggregator Fee
 * @property float $trx_fee
 * @property float $agent_fee
 * @property string|null $api_key
 * @property string|null $api_secret
 * @property MerchantStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \App\Models\User|null $agent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MerchantFeature> $features
 * @property-read int|null $features_count
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereAgentFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereApiSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereBusinessDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereBusinessEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereBusinessLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereBusinessName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereBusinessTelegramGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereBusinessWhatsappGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereMaFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereMerchantKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereSiteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereTrxFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Merchant extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'merchant_key',
        'business_name',
        'site_url',
        'currency_id',
        'business_logo',
        'business_description',
        'business_email',
        'business_whatsapp_group_id',
        'business_telegram_group_id',
        'business_email_group',
        'ma_fee',
        'trx_fee',
        'agent_fee',
        'api_key',
        'api_secret',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'ma_fee' => 'float',
            'trx_fee' => 'double',
            'agent_fee' => 'double',
            'status' => MerchantStatus::class,
        ];
    }

    /**
     * Get the user that owns the merchant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the agent that owns the merchant.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Get the merchant features/settings.
     */
    public function features(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MerchantFeature::class); // ->orderBy('sort_order');
    }

    /**
     * Get a specific merchant feature by name.
     */
    public function getFeature(string $featureName): ?MerchantFeature
    {
        return $this->features()->where('feature', $featureName)->first();
    }

    /**
     * Check if a merchant feature is enabled.
     */
    public function hasFeature(string $featureName, mixed $default = false): bool|string|int|null
    {
        $feature = $this->getFeature($featureName);

        if (! $feature) {
            return $default;
        }

        return $feature->getEffectiveValueAttribute();
    }

    /**
     * Get merchant features grouped by category.
     */
    public function getFeaturesByCategory(): array
    {
        return $this->features()->get()->groupBy('category')->toArray();
    }

    /**
     * Initialize merchant features from config.
     */
    public function initializeFeatures(): void
    {
        MerchantFeature::syncWithConfigForMerchant($this->id);
    }

    /**
     * Check if webhooks are enabled for this merchant.
     *
     * @return bool True if webhooks are enabled
     */
    public function isWebhookEnabled(): bool
    {
        return $this->hasFeature('webhooks_enabled', false);
    }

    /**
     * Get webhook configuration for this merchant.
     *
     * @return array Webhook configuration with url, secret, and verify_ssl
     */
    public function getWebhookConfig(): array
    {
        if (! $this->isWebhookEnabled()) {
            return [];
        }

        return [
            'url' => $this->hasFeature('webhook_url'),
            'secret' => $this->hasFeature('webhook_secret'),
            'verify_ssl' => $this->hasFeature('webhook_verify_ssl', true),
        ];
    }

    /**
     * Get webhook URL for this merchant.
     *
     * @return string|null Webhook URL or null if not configured
     */
    public function getWebhookUrl(): ?string
    {
        return $this->hasFeature('webhook_url');
    }

    /**
     * Get webhook secret for this merchant.
     *
     * @return string|null Webhook secret or null if not configured
     */
    public function getWebhookSecret(): ?string
    {
        return $this->hasFeature('webhook_secret');
    }

    public function totalPaymentReceive(): int
    {
        return Transaction::where([
            'user_id' => $this->user_id,
            'trx_type' => TrxType::RECEIVE_PAYMENT->value,
            'status' => TrxStatus::COMPLETED->value,
        ])->sum('net_amount');
    }
}
