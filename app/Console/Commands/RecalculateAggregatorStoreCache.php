<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AggregatorStoreDailyCache;
use App\Models\Merchant\AggregatorStore;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecalculateAggregatorStoreCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aggregator-store:recalculate-cache
                            {--date-from= : Start date for calculation (Y-m-d format, defaults to today)}
                            {--date-to= : End date for calculation (Y-m-d format, defaults to date-from)}
                            {--merchant-aggregator-id= : Filter by specific aggregator ID}
                            {--dry-run : Preview the calculation without persisting results}
                            {--clear : Clear existing cache for the date range before calculating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate and populate aggregator store daily cache statistics.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dateFrom = $this->option('date-from') ? Carbon::parse($this->option('date-from'))->startOfDay() : now()->startOfDay();
        $dateTo = $this->option('date-to') ? Carbon::parse($this->option('date-to'))->endOfDay() : $dateFrom->copy()->endOfDay();
        $merchantAggregatorId = $this->option('merchant-aggregator-id');
        $dryRun = (bool) $this->option('dry-run');
        $clear = (bool) $this->option('clear');

        if ($dateFrom->gt($dateTo)) {
            $this->error('Start date must be before or equal to end date.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Recalculating aggregator store cache%s from %s to %s...',
            $dryRun ? ' (dry run)' : '',
            $dateFrom->format('Y-m-d'),
            $dateTo->format('Y-m-d')
        ));

        if ($merchantAggregatorId) {
            $this->info(sprintf('Filtering by merchant aggregator ID: %s', $merchantAggregatorId));
        }

        if ($clear && ! $dryRun) {
            $this->info('Clearing existing cache for date range...');
            $this->clearCache($dateFrom, $dateTo, $merchantAggregatorId);
        }

        if ($dryRun) {
            $this->previewCalculation($dateFrom, $dateTo, $merchantAggregatorId);

            return self::SUCCESS;
        }

        $totalProcessed = 0;
        $totalCached = 0;

        DB::transaction(function () use ($dateFrom, $dateTo, $merchantAggregatorId, &$totalProcessed, &$totalCached): void {
            $currentDate = $dateFrom->copy();

            while ($currentDate->lte($dateTo)) {
                $dateStr = $currentDate->format('Y-m-d');
                $startDateStr = $currentDate->startOfDay()->format('Y-m-d H:i:s');
                $endDateStr = $currentDate->copy()->endOfDay()->format('Y-m-d H:i:s');

                $this->line(sprintf('Processing date: %s', $dateStr));

                // Get stores
                $storesQuery = AggregatorStore::select(['id', 'merchant_id', 'merchant_name', 'merchant_aggregator_id']);

                if ($merchantAggregatorId) {
                    $storesQuery->where('merchant_aggregator_id', $merchantAggregatorId);
                }

                $stores = $storesQuery->get();
                $totalProcessed += $stores->count();

                // Calculate and cache for this date
                $cached = $this->calculateAndCacheStoreStats($stores, $dateStr, $startDateStr, $endDateStr);
                $totalCached += $cached;

                $currentDate->addDay();
            }
        });

        if ($totalCached === 0) {
            $this->warn('No cache entries were created/updated.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Aggregator store cache recalculated: %d stores processed, %d cache entries created/updated.',
            $totalProcessed,
            $totalCached
        ));

        return self::SUCCESS;
    }

    /**
     * Calculate and cache store statistics for the given date
     */
    private function calculateAndCacheStoreStats(Collection $stores, string $dateStr, string $startDateStr, string $endDateStr): int
    {
        $merchantIds = $stores->pluck('merchant_id')->toArray();

        if (empty($merchantIds)) {
            return 0;
        }

        $transactionTable = config('app.mode') === 'sandbox' ? 'transactions_sandbox' : 'transactions';

        // Calculate statistics from transactions
        $stats = DB::table($transactionTable)
            ->select([
                'merchant_aggregator_store_nmid as merchant_id',
                DB::raw('COUNT(*) as total_transactions_count'),
                DB::raw('COALESCE(SUM(payable_amount), 0) as total_payable_amount'),
            ])
            ->whereIn('merchant_aggregator_store_nmid', $merchantIds)
            ->whereIn('trx_type', ['deposit', 'receive_payment'])
            ->where('status', 'completed')
            ->whereBetween('updated_at', [$startDateStr, $endDateStr])
            ->groupBy('merchant_aggregator_store_nmid')
            ->get();

        $cached = 0;

        // Cache the results
        foreach ($stores as $store) {
            $stat = $stats->firstWhere('merchant_id', $store->merchant_id);

            AggregatorStoreDailyCache::updateOrCreate(
                [
                    'date' => $dateStr,
                    'merchant_id' => $store->merchant_id,
                ],
                [
                    'merchant_aggregator_id' => $store->merchant_aggregator_id,
                    'total_payable_amount' => $stat ? (float) $stat->total_payable_amount : 0,
                    'total_transactions_count' => $stat ? (int) $stat->total_transactions_count : 0,
                ]
            );

            $cached++;
        }

        return $cached;
    }

    /**
     * Clear cache for the given date range
     */
    private function clearCache(Carbon $dateFrom, Carbon $dateTo, ?string $merchantAggregatorId): void
    {
        $query = AggregatorStoreDailyCache::whereBetween('date', [
            $dateFrom->format('Y-m-d'),
            $dateTo->format('Y-m-d'),
        ]);

        if ($merchantAggregatorId) {
            $query->where('merchant_aggregator_id', $merchantAggregatorId);
        }

        $deleted = $query->delete();
        $this->info(sprintf('Cleared %d cache entries.', $deleted));
    }

    /**
     * Preview the calculation results in a table.
     */
    private function previewCalculation(Carbon $dateFrom, Carbon $dateTo, ?string $merchantAggregatorId): void
    {
        $headers = ['Date', 'Merchant ID', 'Merchant Name', 'Total Payable', 'Transaction Count'];
        $rows = [];

        $transactionTable = config('app.mode') === 'sandbox' ? 'transactions_sandbox' : 'transactions';
        $currentDate = $dateFrom->copy();

        while ($currentDate->lte($dateTo)) {
            $dateStr = $currentDate->format('Y-m-d');
            $startDateStr = $currentDate->startOfDay()->format('Y-m-d H:i:s');
            $endDateStr = $currentDate->copy()->endOfDay()->format('Y-m-d H:i:s');

            // Get stores
            $storesQuery = AggregatorStore::select(['id', 'merchant_id', 'merchant_name', 'merchant_aggregator_id']);

            if ($merchantAggregatorId) {
                $storesQuery->where('merchant_aggregator_id', $merchantAggregatorId);
            }

            $stores = $storesQuery->get();
            $merchantIds = $stores->pluck('merchant_id')->toArray();

            if (! empty($merchantIds)) {
                // Calculate statistics
                $stats = DB::table($transactionTable)
                    ->select([
                        'merchant_aggregator_store_nmid as merchant_id',
                        DB::raw('COUNT(*) as total_transactions_count'),
                        DB::raw('COALESCE(SUM(payable_amount), 0) as total_payable_amount'),
                    ])
                    ->whereIn('merchant_aggregator_store_nmid', $merchantIds)
                    ->whereIn('trx_type', ['deposit', 'receive_payment'])
                    ->where('status', 'completed')
                    ->whereBetween('updated_at', [$startDateStr, $endDateStr])
                    ->groupBy('merchant_aggregator_store_nmid')
                    ->get();

                foreach ($stores as $store) {
                    $stat = $stats->firstWhere('merchant_id', $store->merchant_id);

                    $rows[] = [
                        $dateStr,
                        $store->merchant_id,
                        $store->merchant_name,
                        number_format($stat ? (float) $stat->total_payable_amount : 0, 2),
                        $stat ? (int) $stat->total_transactions_count : 0,
                    ];
                }
            }

            $currentDate->addDay();
        }

        if (empty($rows)) {
            $this->warn('No data found for the given filters.');

            return;
        }

        $this->table($headers, $rows);
        $this->info(sprintf('Total cache entries that would be created/updated: %d', count($rows)));
    }
}
