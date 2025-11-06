<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\TransactionStat;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecalculateTransactionStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stats:recalculate
                            {--status=completed : Filter transactions by status before aggregating}
                            {--dry-run : Preview the aggregation without persisting results}
                            {--chunk=500 : Chunk size when iterating aggregation results}';

    /**
     * The console command description.
     */
    protected $description = 'Rebuild the transaction statistics table by summing net amounts per merchant and transaction type.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $statusFilter = (string) $this->option('status');
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        if ($chunkSize < 1) {
            $this->error('Chunk size must be a positive integer.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Recalculating transaction stats%s%s...',
            $dryRun ? ' (dry run)' : '',
            $statusFilter !== '' ? sprintf(' with status "%s"', $statusFilter) : ''
        ));

        if ($dryRun) {
            $this->previewAggregation($statusFilter, $chunkSize);

            return self::SUCCESS;
        }

        $totalGroups = 0;
        $totalInserted = 0;

        DB::transaction(function () use ($statusFilter, $chunkSize, &$totalGroups, &$totalInserted): void {
            TransactionStat::query()->delete();

            foreach ($this->statDimensions() as $dimension) {
                $this->buildAggregationQuery($statusFilter, $dimension['column'])
                    ->orderBy($dimension['column'])
                    ->chunk($chunkSize, function (Collection $chunk) use (&$totalGroups, &$totalInserted, $dimension): void {
                        $insertRows = [];
                        $now = now();
                        $totalGroups += $chunk->count();

                        foreach ($chunk as $row) {
                            $trxType = TrxType::tryFrom((string) $row->trx_type);

                            if (! $trxType) {
                                $this->warn(sprintf(
                                    'Skipping unknown transaction type "%s" for %s %s.',
                                    $row->trx_type,
                                    strtolower($dimension['label']),
                                    $row->model_id
                                ));

                                continue;
                            }

                            $insertRows[] = [
                                'model_id' => (int) $row->model_id,
                                'model_type' => $dimension['model'],
                                'type' => $trxType->value,
                                'total_transactions' => (int) $row->total_transactions,
                                'total_amount' => (int) $row->total_net_amount,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }

                        if (! empty($insertRows)) {
                            TransactionStat::query()->insert($insertRows);
                            $totalInserted += count($insertRows);
                        }
                    });
            }
        });

        if ($totalInserted === 0) {
            $this->warn('No aggregation results found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Transaction statistics table rebuilt for %d group(s).', $totalInserted));

        return self::SUCCESS;
    }

    /**
     * Get the model dimensions that should receive aggregated statistics.
     *
     * @return array<int, array{column: string, model: class-string, label: string}>
     */
    protected function statDimensions(): array
    {
        return [
            [
                'column' => 'merchant_id',
                'model' => Merchant::class,
                'label' => 'Merchant',
            ],
            [
                'column' => 'user_id',
                'model' => User::class,
                'label' => 'User',
            ],
        ];
    }

    /**
     * Build the base aggregation query.
     */
    protected function buildAggregationQuery(string $statusFilter, string $groupColumn): Builder
    {
        $model = new Transaction;
        $connection = $model->getConnection();
        $table = $model->getTable();

        $query = $connection->table($table)
            ->select($groupColumn.' as model_id', 'trx_type')
            ->selectRaw('SUM(COALESCE(net_amount, 0)) as total_net_amount')
            ->selectRaw('COUNT(*) as total_transactions')
            ->whereNotNull($groupColumn)
            ->whereNotNull('trx_type')
            ->groupBy($groupColumn, 'trx_type');

        if ($statusFilter !== '' && strtolower($statusFilter) !== 'all') {
            $statusEnum = TrxStatus::tryFrom($statusFilter);
            $status = $statusEnum ? $statusEnum->value : $statusFilter;
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * Preview the aggregation results in a table.
     */
    protected function previewAggregation(string $statusFilter, int $chunkSize): void
    {
        $headers = ['Model Type', 'Model ID', 'Transaction Type', 'Total Transactions', 'Total Net Amount'];
        $rows = [];

        foreach ($this->statDimensions() as $dimension) {
            $this->buildAggregationQuery($statusFilter, $dimension['column'])
                ->orderBy($dimension['column'])
                ->chunk($chunkSize, function (Collection $chunk) use (&$rows, $dimension): void {
                    foreach ($chunk as $row) {
                        $rows[] = [
                            $dimension['label'],
                            (int) $row->model_id,
                            $row->trx_type,
                            (int) $row->total_transactions,
                            $row->total_net_amount,
                        ];
                    }
                });
        }

        $this->table($headers, $rows);
    }
}
