<?php

namespace App\Models;

use App\Constants\CurrencyRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property int $currency_id
 * @property int $user_id
 * @property string $uuid
 * @property float $balance
 * @property float $hold_balance
 * @property float $balance_sandbox
 * @property float $hold_balance_sandbox
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read bool $is_payment
 * @property-read bool $is_receiver
 * @property-read bool $is_request_money
 * @property-read bool $is_sender
 * @property-read bool $is_withdraw
 * @property-read mixed $latest_transaction
 * @property-read mixed $total_balance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereBalanceSandbox($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereHoldBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereHoldBalanceSandbox($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereUuid($value)
 *
 * @mixin \Eloquent
 */
class Wallet extends Model
{
    /**
     * @var array
     */
    protected $fillable = [
        'currency_id',
        'user_id',
        'uuid',
        'balance',
        'balance_sandbox',
        'hold_balance',
        'hold_balance_sandbox',
        'status',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'currency_id' => 'integer',
        'user_id' => 'integer',
        'balance' => 'float',
        'balance_sandbox' => 'float',
        'hold_balance' => 'float',
        'hold_balance_sandbox' => 'float',
        'uuid' => 'string',
        'status' => 'boolean',
    ];

    /**
     * Scope to filter inactive wallets.
     */
    public function scope($query)
    {
        return $query->where('wallets.status', false);
    }

    /**
     * Get the transactions for the wallet.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'wallet_reference', 'uuid');
    }

    /**
     * Get the latest transaction for the wallet.
     */
    public function getLatestTransactionAttribute()
    {
        return $this->transactions()->latest('created_at')->first();
    }

    /**
     * Get the user for the wallet.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the currency role info for the wallet.
     */
    public function getCurrencyRoleInfo($role)
    {
        return $this->currency->getRoleInfo($role);
    }

    /**
     * Check if the wallet has a currency role.
     */
    public function hasCurrencyRole(string $role): bool
    {
        return $this->currency->hasRole($role);
    }

    /**
     * Get the is sender attribute for the wallet.
     */
    public function getIsSenderAttribute(): bool
    {
        return $this->hasCurrencyRole(CurrencyRole::SENDER);
    }

    /**
     * Get the is request money attribute for the wallet.
     */
    public function getIsRequestMoneyAttribute(): bool
    {
        return $this->hasCurrencyRole(CurrencyRole::REQUEST_MONEY);
    }

    /**
     * Get the is payment attribute for the wallet.
     */
    public function getIsPaymentAttribute(): bool
    {
        return $this->hasCurrencyRole(CurrencyRole::PAYMENT);
    }

    /**
     * Get the is receiver attribute for the wallet.
     */
    public function getIsReceiverAttribute(): bool
    {
        return $this->hasCurrencyRole(CurrencyRole::RECEIVER);
    }

    /**
     * Get the is withdraw attribute for the wallet.
     */
    public function getIsWithdrawAttribute(): bool
    {
        return $this->hasCurrencyRole(CurrencyRole::WITHDRAW);
    }

    /**
     * Get the balance attribute based on app mode.
     * In sandbox mode, returns balance_sandbox, otherwise returns balance.
     */
    public function getBalanceAttribute($value)
    {
        if (config('app.mode') === 'sandbox') {
            return $this->attributes['balance_sandbox'] ?? 0;
        }

        return $value;
    }

    /**
     * Set the balance attribute based on app mode.
     * In sandbox mode, updates balance_sandbox, otherwise updates balance.
     */
    public function setBalanceAttribute($value)
    {
        if (config('app.mode') === 'sandbox') {
            $this->attributes['balance_sandbox'] = $value;
        } else {
            $this->attributes['balance'] = $value;
        }
    }

    /**
     * Get the hold balance attribute based on app mode.
     */
    public function getHoldBalanceAttribute($value)
    {
        if (config('app.mode') === 'sandbox') {
            return $this->attributes['hold_balance_sandbox'] ?? 0;
        }

        return $value ?? 0;
    }

    /**
     * Set the hold balance attribute based on app mode.
     */
    public function setHoldBalanceAttribute($value)
    {
        if (config('app.mode') === 'sandbox') {
            $this->attributes['hold_balance_sandbox'] = $value;
        } else {
            $this->attributes['hold_balance'] = $value;
        }
    }

    /**
     * Get the hold balance field name based on app mode.
     */
    public function getHoldBalanceFieldName(): string
    {
        return config('app.mode') === 'sandbox' ? 'hold_balance_sandbox' : 'hold_balance';
    }

    /**
     * Get the actual hold balance value based on app mode.
     */
    public function getActualHoldBalance(): float
    {
        $fieldName = $this->getHoldBalanceFieldName();

        return (float) ($this->attributes[$fieldName] ?? 0);
    }

    /**
     * Get available balance (balance - hold balance).
     */
    public function getAvailableBalance(): float
    {
        return $this->getActualBalance() - $this->getActualHoldBalance();
    }

    protected function totalBalance(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getActualBalance() - $this->getActualHoldBalance(),
        );
    }

    /**
     * Add funds to hold balance.
     */
    public function addToHoldBalance(float $amount): bool
    {
        $fieldName = $this->getHoldBalanceFieldName();

        return $this->increment($fieldName, $amount);
    }

    /**
     * Release hold funds (unlock from hold balance).
     */
    public function releaseHoldFunds(float $amount): bool
    {
        $holdFieldName = $this->getHoldBalanceFieldName();

        if ($this->getActualHoldBalance() < $amount) {
            return false;
        }

        // Only decrease hold balance; available balance is implicitly increased
        return $this->decrement($holdFieldName, $amount);
    }

    /**
     * Get the actual balance field name based on app mode.
     */
    public function getBalanceFieldName(): string
    {
        return config('app.mode') === 'sandbox' ? 'balance_sandbox' : 'balance';
    }

    /**
     * Get the actual balance value based on app mode.
     */
    public function getActualBalance(): float
    {
        $fieldName = $this->getBalanceFieldName();

        return (float) $this->attributes[$fieldName] ?? 0;
    }

    /**
     * Update balance based on app mode.
     */
    public function updateBalance(float $amount): bool
    {
        $fieldName = $this->getBalanceFieldName();

        return $this->update([$fieldName => $amount]);
    }

    /**
     * Increment balance based on app mode.
     */
    public function incrementBalance(float $amount): bool
    {
        $fieldName = $this->getBalanceFieldName();

        return $this->increment($fieldName, $amount);
    }

    /**
     * Decrement balance based on app mode.
     */
    public function decrementBalance(float $amount): bool
    {
        $fieldName = $this->getBalanceFieldName();

        return $this->decrement($fieldName, $amount);
    }
}
