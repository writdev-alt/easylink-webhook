<?php

namespace App\Jobs;

use App\Models\AggregatorStoreDailyCache;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wrpay\Core\Models\Transaction;

class UpdateAggregatorStoreDailyCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(public Transaction $transaction)
    {
    }

    public function handle(): void
    {
        $storeId = $this->transaction->merchant_aggregator_store_nmid ?? null;

        if (! $storeId) {
            return;
        }

        $date = now()->toDateString();
        $amount = (float) $this->transaction->payable_amount;

        $cache = AggregatorStoreDailyCache::firstOrCreate(
            ['date' => $date, 'merchant_id' => $storeId],
            [
                'merchant_aggregator_id' => config('app.ma_instance'),
                'total_payable_amount' => 0,
                'total_transactions_count' => 0,
            ]
        );

        AggregatorStoreDailyCache::where('date', $cache->date)
            ->where('merchant_id', $cache->merchant_id)
            ->update([
                'total_payable_amount' => DB::raw('total_payable_amount + '.$amount),
                'total_transactions_count' => DB::raw('total_transactions_count + 1'),
                'updated_at' => now(),
            ]);

        $updated = AggregatorStoreDailyCache::where('date', $cache->date)
            ->where('merchant_id', $cache->merchant_id)
            ->first();

        if (! $updated) {
            return;
        }

        $limit = config('app.ma_merchant_store_limit') * 1_000_000;

        if ($updated->total_payable_amount >= $limit) {
            Cache::forever("aggregator_store_disabled:{$cache->merchant_id}", true);
            Log::warning('Aggregator store daily limit reached; disabling aggregator_store.', [
                'merchant_id' => $cache->merchant_id,
                'date' => $cache->date,
                'total_payable_amount' => $updated->total_payable_amount,
                'total_transactions_count' => $updated->total_transactions_count,
                'limit' => $limit,
            ]);
        }
    }
}

