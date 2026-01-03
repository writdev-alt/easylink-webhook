<?php

namespace App\Models\Merchant;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Pdp\Domain;
use Pdp\Rules;
use Sqits\UserStamps\Concerns\HasUserStamps;

/**
 * @property int $id
 * @property int $merchant_aggregator_id
 * @property string $merchant_id
 * @property string $merchant_name
 * @property string $merchant_nmid
 * @property string $merchant_bank_name
 * @property string $merchant_qr_static_url
 * @property string|null $merchant_qr_static_content
 * @property string|null $billing_province
 * @property \Illuminate\Support\Carbon $merchant_created_at
 * @property bool $status true for publish, false for unpublish
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Merchant\Aggregator $aggregator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereBillingProvince($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereMerchantAggregatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereMerchantBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereMerchantCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereMerchantName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereMerchantNmid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereMerchantQrStaticContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereMerchantQrStaticUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereUpdatedAt($value)
 *
 * @property string|null $merchant_bank
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereMerchantBank($value)
 *
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereUpdatedBy($value)
 *
 * @property string|null $uuid
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AggregatorStore whereUuid($value)
 *
 * @mixin \Eloquent
 */
class AggregatorStore extends Model
{
    use HasFactory;
    //    use HasUserStamps;

    protected $table;

    /**
     * Create a new Eloquent model instance.
     *
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Use sandbox table when app is in sandbox mode
        if (config('app.mode') === 'sandbox') {
            $this->setTable('merchant_aggregator_stores_sandbox');
        } else {
            $this->setTable('merchant_aggregator_stores');
        }
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'merchant_aggregator_id',
        'merchant_id',
        'merchant_name',
        'merchant_nmid',
        'merchant_bank_name',
        'merchant_qr_static_url',
        'merchant_created_at',
        'status',
        'merchant_qr_static_content',
        'billing_province',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'merchant_created_at' => 'datetime',
        'status' => 'boolean',
    ];

    /**
     * Get the aggregator that owns the store.
     */
    public function aggregator(): BelongsTo
    {
        return $this->belongsTo(Aggregator::class, 'merchant_aggregator_id');
    }

    /**
     * Get the transactions for the store.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'merchant_aggregator_store_nmid', 'merchant_id');
    }

    public static function getLimitOfTransactions(): float
    {
        return 50000000; // 50 million
    }

    /**
     * Get merchant NMIDs that have not reached the transaction limit of 50,000,000.
     * Only returns stores from aggregators with matching domain unless $allDomains is true.
     *
     * @param  bool  $allDomains  If true, bypass domain filtering and return stores from all domains
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAvailableStore(bool $allDomains = false)
    {
        $limit = static::getLimitOfTransactions();

        return static::fetchFreshStores($allDomains, $limit);
    }

    /**
     * Fetch fresh stores from database with domain and transaction limit filters
     *
     * @param  bool  $allDomains
     * @param  float  $limit
     * @return \App\Models\Merchant\AggregatorStore|null
     */
    private static function fetchFreshStores($allDomains, $limit)
    {
        $query = static::query();
        $aggregatorStoreTable = static::make()->getTable();

        // Cek apakah mode saat ini adalah sandbox
        $isSandboxMode = (config('app.mode') === 'sandbox');

        // Apply domain filter only if $allDomains is false
        if (! $allDomains) {
            // Get current domain logic
            $currentDomain = request()->getHost();

            // Hapus prefix 'sandbox.' jika ada, agar domain selalu bersih saat mencari di tabel Aggregator
            $currentDomain = str_replace('sandbox.', '', $currentDomain);

            // Handle localhost .test domain - treat as .com
            if (str_ends_with($currentDomain, '.test')) {
                $currentDomain = str_replace('.test', '.com', $currentDomain);
            }

            // Normalize to registrable (master) domain using Public Suffix List
            $currentDomain = static::getRegistrableDomain($currentDomain);
            Log::info('Current Domain being filtered', ['currentDomain' => $currentDomain]);

            // Filter AggregatorStore based on the domain of the linked Aggregator.
            // **PENTING: Menghapus filter is_sandbox di Aggregator sesuai permintaan Anda**
            $query->whereHas('aggregator', function ($subQuery) use ($currentDomain) {
                $subQuery->where(function ($domainQuery) use ($currentDomain) {
                    $domainQuery->where('domain', $currentDomain);
                });
            });
        }

        // Ambil hasil secara acak
        $query->inRandomOrder();
        $result = $query->first();

        // Log the result (optional, but useful for debugging)
        Log::info('getAvailableStore result', ['result' => $result ? $result->merchant_nmid : 'null']);

        return $result;
    }

    /**
     * Convert a host to its registrable (apex) domain using the Public Suffix List.
     * Falls back to a simple heuristic if PSL loading fails.
     */
    private static function getRegistrableDomain(string $host): string
    {
        try {
            $domain = Domain::fromIDNA2008($host);

            $pslPath = storage_path('app/public_suffix_list.dat');

            $needsRefresh = ! file_exists($pslPath) || (time() - @filemtime($pslPath) > 60 * 60 * 24 * 30);
            if ($needsRefresh) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'user_agent' => 'php-domain-parser-client',
                    ],
                ]);
                $data = @file_get_contents('https://publicsuffix.org/list/public_suffix_list.dat', false, $context);
                if ($data) {
                    @file_put_contents($pslPath, $data);
                }
            }

            if (file_exists($pslPath) && filesize($pslPath) > 0) {
                $rules = Rules::fromPath($pslPath);
                $result = $rules->resolve($domain);
                $registrable = $result->registrableDomain()->toString();
                if ($registrable !== '') {
                    return $registrable;
                }
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }

        // Fallback: naive extraction
        $parts = explode('.', $host);
        $count = count($parts);
        if ($count <= 2) {
            return $host;
        }
        $twoLast = $parts[$count - 2].'.'.$parts[$count - 1];
        $multiPartTlds = [
            'co.id', 'co.uk', 'com.au', 'co.jp', 'com.br', 'com.mx', 'com.cn', 'com.sg', 'co.in', 'com.my',
        ];
        if (in_array($twoLast, $multiPartTlds, true) && $count >= 3) {
            return $parts[$count - 3].'.'.$twoLast;
        }

        return $twoLast;
    }

    /**
     * Check if this store has reached the transaction limit of 50,000,000 for today.
     */
    public function isStoreReachLimitOfTransactions(): bool
    {
        $limit = static::getLimitOfTransactions();

        $totalPayableAmount = $this->transactions()
            ->whereIn('trx_type', ['deposit', 'receive_payment'])
            ->whereDate('created_at', today())
            ->sum('payable_amount');

        return $totalPayableAmount >= $limit;
    }

    /**
     * Get total amount of transactions for today.
     */
    public function getTodayTransactionAmount(): float
    {
        return $this->transactions()
            ->whereIn('trx_type', ['deposit', 'receive_payment'])
            ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('payable_amount') ?? 0;
    }

    /**
     * Get total amount of transactions for this month.
     */
    public function getThisMonthTransactionAmount(): float
    {
        return $this->transactions()
            ->whereIn('trx_type', ['deposit', 'receive_payment'])
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->where('status', 'completed')
            ->sum('payable_amount') ?? 0;
    }

    /**
     * Get total amount of transactions for this year.
     */
    public function getThisYearTransactionAmount(): float
    {
        return $this->transactions()
            ->whereIn('trx_type', ['deposit', 'receive_payment'])
            ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()])
            ->where('status', 'completed')
            ->sum('payable_amount') ?? 0;
    }

    /**
     * Get total amount of all transactions.
     */
    public function getTotalTransactionAmount(): float
    {
        return $this->transactions()
            ->whereIn('trx_type', ['deposit', 'receive_payment'])
            ->where('status', 'completed')
            ->sum('payable_amount') ?? 0;
    }
}
