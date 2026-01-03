<?php

namespace App\Models\Merchant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sqits\UserStamps\Concerns\HasUserStamps;

/**
 * @property int $id
 * @property string|null $domain
 * @property string $legal_name
 * @property string $legal_code
 * @property int $is_sandbox
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Merchant\AggregatorConfig> $configs
 * @property-read int|null $configs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Merchant\AggregatorStore> $stores
 * @property-read int|null $stores_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 *
 * @method static Builder<static>|Aggregator newModelQuery()
 * @method static Builder<static>|Aggregator newQuery()
 * @method static Builder<static>|Aggregator query()
 * @method static Builder<static>|Aggregator whereCreatedAt($value)
 * @method static Builder<static>|Aggregator whereDomain($value)
 * @method static Builder<static>|Aggregator whereId($value)
 * @method static Builder<static>|Aggregator whereIsSandbox($value)
 * @method static Builder<static>|Aggregator whereLegalCode($value)
 * @method static Builder<static>|Aggregator whereLegalName($value)
 * @method static Builder<static>|Aggregator whereUpdatedAt($value)
 *
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $deleted_at
 *
 * @method static Builder<static>|Aggregator whereCreatedBy($value)
 * @method static Builder<static>|Aggregator whereDeletedAt($value)
 * @method static Builder<static>|Aggregator whereDeletedBy($value)
 * @method static Builder<static>|Aggregator whereUpdatedBy($value)
 *
 * @property string|null $uuid
 *
 * @method static Builder<static>|Aggregator whereUuid($value)
 *
 * @mixin \Eloquent
 */
class Aggregator extends Model
{
    use HasFactory;
    //    use HasUserStamps;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'merchant_aggregators';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'legal_name',
        'legal_code',
        'is_sandbox',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void {}

    /**
     * Get the configurations for the aggregator.
     */
    public function configs(): HasMany
    {
        return $this->hasMany(AggregatorConfig::class, 'merchant_aggregator_id');
    }

    /**
     * Get the stores for the aggregator.
     */
    public function stores(): HasMany
    {
        return $this->hasMany(AggregatorStore::class, 'merchant_aggregator_id');
    }

    /**
     * Get transactions through aggregator stores.
     */
    public function transactions()
    {
        return $this->hasManyThrough(
            \App\Models\Transaction::class,
            AggregatorStore::class,
            'merchant_aggregator_id', // Foreign key on aggregator_stores table
            'merchant_aggregator_store_nmid', // Foreign key on transactions table
            'id', // Local key on aggregators table
            'merchant_nmid' // Local key on aggregator_stores table
        );
    }

    /**
     * Get total transactions amount for today.
     */
    public function getTotalAmountOfTransactionsToday(): float
    {
        $transactionTable = config('app.mode') === 'sandbox' ? 'transactions_sandbox' : 'transactions';

        return $this->transactions()
            ->whereIn($transactionTable.'.trx_type', ['deposit', 'receive_payment'])
            ->whereDate($transactionTable.'.created_at', now()->toDateString())
            ->where($transactionTable.'.status', 'completed')
            ->sum('payable_amount') ?? 0;
    }

    /**
     * Get total transactions amount for this month.
     */
    public function getTotalAmountOfTransactionsThisMonth(): float
    {
        $transactionTable = config('app.mode') === 'sandbox' ? 'transactions_sandbox' : 'transactions';

        return $this->transactions()
            ->whereIn($transactionTable.'.trx_type', ['deposit', 'receive_payment'])
            ->whereBetween($transactionTable.'.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->where($transactionTable.'.status', 'completed')
            ->sum('payable_amount') ?? 0;
    }

    /**
     * Get total transactions amount for this year.
     */
    public function getTotalAmountOfTransactionsThisYear(): float
    {
        $transactionTable = config('app.mode') === 'sandbox' ? 'transactions_sandbox' : 'transactions';

        return $this->transactions()
            ->whereIn($transactionTable.'.trx_type', ['deposit', 'receive_payment'])
            ->whereBetween($transactionTable.'.created_at', [now()->startOfYear(), now()->endOfYear()])
            ->where($transactionTable.'.status', 'completed')
            ->sum('payable_amount') ?? 0;
    }

    /**
     * Get total transactions amount (all time).
     */
    public function getTotalAmountOfTransactions(): float
    {
        $transactionTable = config('app.mode') === 'sandbox' ? 'transactions_sandbox' : 'transactions';

        return $this->transactions()
            ->whereIn('trx_type', ['deposit', 'receive_payment'])
            ->where($transactionTable.'.status', 'completed')
            ->sum('payable_amount') ?? 0;
    }

    /**
     * Get number of transactions for today.
     */
    public function getNumOfTransactionsToday(): int
    {
        $transactionTable = config('app.mode') === 'sandbox' ? 'transactions_sandbox' : 'transactions';

        return $this->transactions()
            ->whereIn($transactionTable.'.trx_type', ['deposit', 'receive_payment'])
            ->whereDate($transactionTable.'.created_at', now()->toDateString())
            ->where($transactionTable.'.status', 'completed')
            ->count();
    }

    /**
     * Get number of transactions for this month.
     */
    public function getNumOfTransactionsThisMonth(): int
    {
        $transactionTable = config('app.mode') === 'sandbox' ? 'transactions_sandbox' : 'transactions';

        return $this->transactions()
            ->whereIn($transactionTable.'.trx_type', ['deposit', 'receive_payment'])
            ->whereBetween($transactionTable.'.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->where($transactionTable.'.status', 'completed')
            ->count();
    }

    /**
     * Get number of transactions for this year.
     */
    public function getNumOfTransactionsThisYear(): int
    {
        $transactionTable = config('app.mode') === 'sandbox' ? 'transactions_sandbox' : 'transactions';

        return $this->transactions()
            ->whereIn($transactionTable.'.trx_type', ['deposit', 'receive_payment'])
            ->whereBetween($transactionTable.'.created_at', [now()->startOfYear(), now()->endOfYear()])
            ->where($transactionTable.'.status', 'completed')
            ->count();
    }

    /**
     * Get total number of transactions (all time).
     */
    public function getNumOfTransactions(): int
    {
        return $this->transactions()
            ->whereIn('trx_type', ['deposit', 'receive_payment'])
            ->count();
    }

    /**
     * Get easylink balance for this aggregator.
     * Retrieves the actual balance from EasyLink API using aggregator-specific credentials.
     */
    public function getEasylinkBalance(): float
    {
        try {
            // Get EasyLink configuration for this aggregator
            $easylinkConfig = $this->configs()
                ->where('config_key', 'easylink')
                ->first();

            if (! $easylinkConfig) {
                \Log::warning("EasyLink config not found for aggregator: {$this->legal_name} (ID: {$this->id})");

                return 0.00;
            }

            // Get credentials based on current app mode
            $credentials = config('app.mode') === 'production'
                ? $easylinkConfig->config_production
                : $easylinkConfig->config_sandbox;

            if (empty($credentials)) {
                \Log::warning("EasyLink credentials not found for aggregator: {$this->legal_name} in ".config('app.mode').' mode');

                return 0.00;
            }

            // Create EasyLink gateway instance with aggregator-specific credentials
            $gateway = new \App\Payment\Easylink\EasylinkPaymentGateway($credentials);

            // Get account balances
            $balanceData = $gateway->getAccountBalances();

            // Extract the main balance amount (assuming it's in the first balance entry)
            if (isset($balanceData->balance) && $balanceData->balance > 0) {
                return (float) ($balanceData->balance ?? 0.00);
            }

            return 0.00;

        } catch (\Exception $e) {
            \Log::error("Failed to get EasyLink balance for aggregator {$this->legal_name}: ".$e->getMessage());

            return 0.00;
        }
    }
}
