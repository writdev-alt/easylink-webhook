<?php

namespace App\Models;

use App\Enums\AmountFlow;
use App\Enums\MethodType;
use App\Enums\TrxStatus;
use App\Enums\TrxType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $merchant_aggregator_store_nmid
 * @property int|null $merchant_id
 * @property int $user_id
 * @property int|null $customer_id
 * @property string $trx_id
 * @property TrxType $trx_type
 * @property string|null $description
 * @property string|null $provider
 * @property int|null $method_id ID of the related method (deposit_method or withdraw_method)
 * @property string|null $method_type Type of method (App\Models\DepositMethod or App\Models\WithdrawMethod)
 * @property MethodType $processing_type
 * @property float $amount
 * @property AmountFlow|null $amount_flow
 * @property float $ma_fee Merchant Aggregator Fee
 * @property float $mdr_fee Merchant Discount Rate Fee
 * @property float $admin_fee Admin Fee
 * @property float $agent_fee Agent Fee
 * @property float $cashback_fee MDR Cashback Fee from PG
 * @property float $trx_fee Total Transaction Fee ma_fee+mdr_fee+admin_fee+agent_fee
 * @property string $currency
 * @property int $net_amount
 * @property float|null $payable_amount
 * @property string|null $payable_currency
 * @property string|null $wallet_reference
 * @property string|null $trx_reference
 * @property array<array-key, mixed>|null $trx_data
 * @property string|null $remarks
 * @property TrxStatus $status
 * @property \Illuminate\Support\Carbon|null $released_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $webhook_call
 * @property int|null $webhook_call_sent
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Support\Collection $merchant_features
 * @property-read \App\Models\Merchant|null $merchant
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MerchantFeature> $merchantFeatures
 * @property-read int|null $merchant_features_count
 * @property-read Model|\Eloquent|null $method
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Wallet|null $wallet
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction readyForRelease()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAdminFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAgentFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmountFlow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCashbackFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMaFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMdrFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMerchantAggregatorStoreNmid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMethodType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereNetAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePayableAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePayableCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereProcessingType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereReleasedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTrxData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTrxFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTrxId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTrxReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTrxType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereWalletReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereWebhookCall($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereWebhookCallSent($value)
 * @mixin \Eloquent
 */
class Transaction extends Model
{
    /**
     * @var array
     */
    protected $fillable = [
        'user_id',
        'customer_id',
        'trx_id',
        'trx_type',
        'description',
        'provider',
        'processing_type',
        'amount',
        'amount_flow',
        'ma_fee',
        'mdr_fee',
        'admin_fee',
        'agent_fee',
        'cashback_fee',
        'trx_fee',
        'currency',
        'net_amount',
        'payable_amount',
        'payable_currency',
        'wallet_reference',
        'trx_reference',
        'trx_data',
        'remarks',
        'status',
        'merchant_id',
        'merchant_aggregator_store_nmid',
        'method_id',
        'method_type',
        'recipient',
        'sender',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'trx_type' => TrxType::class,
            'processing_type' => MethodType::class,
            'status' => TrxStatus::class,
            'amount_flow' => AmountFlow::class,
            'trx_data' => 'array',
            'ma_fee' => 'float',
            'mdr_fee' => 'float',
            'admin_fee' => 'float',
            'agent_fee' => 'float',
            'cashback_fee' => 'float',
            'trx_fee' => 'float',
            'amount' => 'float',
            'net_amount' => 'integer',
            'payable_amount' => 'float',
            'released_at' => 'datetime',
        ];
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        if (config('app.mode') === 'sandbox') {
            $this->setTable('transactions_sandbox');
        } else {
            $this->setTable('transactions');
        }
    }

    /**
     * Check if the transaction funds are hold.
     */
    public function isHold(): bool
    {
        return $this->trx_type === TrxType::RECEIVE_PAYMENT
            && $this->status === TrxStatus::COMPLETED
            && is_null($this->released_at);
    }

    /**
     * Check if the transaction funds can be released (H+1).
     */
    public function canBeReleased(): bool
    {
        if (! $this->isHold()) {
            return false;
        }

        $releaseDate = $this->created_at->copy()->addDay()->startOfDay();

        return now()->greaterThanOrEqualTo($releaseDate);
    }

    /**
     * Get the release date for this transaction.
     */
    public function getReleaseDate(): ?\Carbon\Carbon
    {
        if ($this->trx_type !== TrxType::RECEIVE_PAYMENT) {
            return null;
        }

        return $this->created_at->copy()->addDay()->startOfDay();
    }

    /**
     * Scope to get hold transactions that can be released.
     */
    public function scopeReadyForRelease($query)
    {
        $releaseDate = now()->subDay()->setTime(23, 0, 0);

        return $query->where('trx_type', TrxType::RECEIVE_PAYMENT)
            ->where('status', TrxStatus::COMPLETED)
            ->whereNull('released_at')
            ->where('created_at', '<', $releaseDate);
    }

    /**
     * @var array
     */
    protected $attributes = [
        'trx_fee' => 0,
        'status' => TrxStatus::PENDING,
    ];

    // Scopes

    /**
     * Transaction belongs to a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Transaction belongs to a customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }


    /**
     * Transaction belongs to a merchant.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    /**
     * Polymorphic relationship to method (DepositMethod or WithdrawMethod).
     */
    public function method(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Transaction has many merchant features through merchant.
     * This establishes a many-to-many relationship via the merchant.
     */
    public function merchantFeatures(): HasManyThrough
    {
        return $this->hasManyThrough(
            MerchantFeature::class,
            Merchant::class,
            'id',
            'merchant_id',
            'merchant_id',
            'id'
        );
    }

    /**
     * Get merchant features for this transaction's merchant.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getMerchantFeaturesAttribute(): \Illuminate\Support\Collection
    {
        if ($this->merchant) {
            return $this->merchant->features;
        }

        return collect([]);
    }

    /**
     * Check if a specific merchant feature is enabled for this transaction.
     *
     * @param  string  $featureName  The feature name to check
     * @param  mixed  $default  Default value if feature not found
     * @return mixed The feature value or default
     */
    public function hasMerchantFeature(string $featureName, mixed $default = false): mixed
    {
        if (! $this->merchant) {
            return $default;
        }

        return $this->merchant->hasFeature($featureName, $default);
    }

    /**
     * Check if transaction should auto-process based on merchant settings.
     */
    public function shouldAutoProcess(): bool
    {
        return $this->hasMerchantFeature('auto_process_payments');
    }

    /**
     * Check if webhooks are enabled for this transaction's merchant.
     */
    public function isWebhookEnabled(): bool
    {
        return $this->hasMerchantFeature('webhooks_enabled');
    }

    /**
     * Get webhook configuration for this transaction.
     */
    public function getWebhookConfig(): array
    {
        if (! $this->isWebhookEnabled()) {
            return [];
        }

        return [
            'url' => $this->hasMerchantFeature('webhook_url'),
            'secret' => $this->hasMerchantFeature('webhook_secret'),
            'verify_ssl' => $this->hasMerchantFeature('webhook_verify_ssl', true),
        ];
    }

    /**
     * Check if email notifications are enabled for this transaction.
     */
    public function isEmailNotificationEnabled(): bool
    {
        return $this->hasMerchantFeature('email_notifications', true);
    }

    /**
     * Get notification email for this transaction's merchant.
     */
    public function getNotificationEmail(): ?string
    {
        $email = $this->hasMerchantFeature('notification_email');

        // Fallback to merchant's business email or user email
        if (! $email && $this->merchant) {
            $email = $this->merchant->business_email ?? $this->merchant->user->email;
        }

        return $email;
    }

    /**
     * Get payment timeout in minutes for this transaction.
     */
    public function getPaymentTimeoutMinutes(): int
    {
        return $this->hasMerchantFeature('payment_timeout_minutes', 30);
    }

    /**
     * Get the wallet associated with the transaction.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_reference', 'uuid');
    }

    public function alreadySentAutomaticWebhook(): bool
    {
        return $this->webhook_call !== null;
    }

    public function setWebhookCall(): void
    {
        $this->webhook_call = now();
        $this->save();
    }
}
