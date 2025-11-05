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
        'user_id',
        'currency_id',
        'uuid',
        'balance',
        'hold_balance',
        'balance_sandbox',
        'hold_balance_sandbox',
        'status',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'balance' => 'float',
        'balance_sandbox' => 'float',
        'hold_balance' => 'float',
        'hold_balance_sandbox' => 'float',
        'status' => 'boolean',
    ];

    /**
     * Model-level default attributes.
     * Ensure status defaults to true at creation time.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => true,
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
        return optional($this->currency)->hasRole($role) ?? false;
    }


    /**
     * Get the actual hold balance value based on app mode.
     */
    public function getActualHoldBalance(bool $sandbox = false): float
    {
        $fieldName = $this->getHoldBalanceFieldName($sandbox);

        return (float) ($this->attributes[$fieldName] ?? 0);
    }


    /**
     * Add funds to hold balance.
     */
    public function addToHoldBalance(float $amount): bool
    {
        $fieldName = config('app.mode') === 'sandbox' ? 'hold_balance_sandbox' : 'hold_balance';

        return $this->increment('hold_balance', $amount);
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
    public function getBalanceFieldName(bool $sandbox = false): string
    {
        $isSandbox = $sandbox || config('app.mode') === 'sandbox';

        return $isSandbox ? 'balance_sandbox' : 'balance';
    }

    /**
     * Get the actual balance value based on app mode.
     */
    public function getActualBalance(bool $sandbox = false): float
    {
        $fieldName = $this->getBalanceFieldName($sandbox);

        return (float) ($this->attributes[$fieldName] ?? 0);
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
     * Uses rounding down (floor) for the amount.
     */
    public function incrementBalance(float $amount): bool
    {
        $fieldName = $this->getBalanceFieldName();
        $roundedAmount = floor($amount);

        return $this->increment($fieldName, $roundedAmount);
    }

    /**
     * Decrement balance based on app mode.
     * Uses rounding up (ceil) for the amount.
     */
    public function decrementBalance(float $amount): bool
    {
        $fieldName = $this->getBalanceFieldName();
        $roundedAmount = ceil($amount);

        return $this->decrement($fieldName, $roundedAmount);
    }
}
