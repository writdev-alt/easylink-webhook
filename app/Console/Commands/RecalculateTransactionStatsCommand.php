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

            // First, insert transaction type rows
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

                            // Add transaction type row with payable_amount
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

            // Then, insert fee type rows aggregated across all transaction types
            foreach ($this->statDimensions() as $dimension) {
                $this->buildFeeAggregationQuery($statusFilter, $dimension['column'])
                    ->orderBy($dimension['column'])
                    ->chunk($chunkSize, function (Collection $chunk) use (&$totalInserted, $dimension): void {
                        $insertRows = [];
                        $now = now();

                        foreach ($chunk as $row) {
                            $feeTypes = [
                                'mdr_fee' => (int) $row->total_mdr_fee,
                                'admin_fee' => (int) $row->total_admin_fee,
                                'agent_fee' => (int) $row->total_agent_fee,
                                'cashback_fee' => (int) $row->total_cashback_fee,
                            ];

                            foreach ($feeTypes as $feeType => $feeAmount) {
                                if ($feeAmount > 0) {
                                    $insertRows[] = [
                                        'model_id' => (int) $row->model_id,
                                        'model_type' => $dimension['model'],
                                        'type' => $feeType,
                                        'total_transactions' => (int) $row->total_transactions,
                                        'total_amount' => $feeAmount,
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ];
                                }
                            }
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
     * Build the base aggregation query for transaction types.
     */
    protected function buildAggregationQuery(string $statusFilter, string $groupColumn): Builder
    {
        $model = new Transaction;
        $connection = $model->getConnection();
        $table = $model->getTable();

        $query = $connection->table($table)
            ->select($groupColumn.' as model_id', 'trx_type')
            ->selectRaw('SUM(COALESCE(payable_amount, 0)) as total_net_amount')
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
     * Build the fee aggregation query (aggregated across all transaction types).
     */
    protected function buildFeeAggregationQuery(string $statusFilter, string $groupColumn): Builder
    {
        $model = new Transaction;
        $connection = $model->getConnection();
        $table = $model->getTable();

        $query = $connection->table($table)
            ->select($groupColumn.' as model_id')
            ->selectRaw('SUM(COALESCE(mdr_fee, 0)) as total_mdr_fee')
            ->selectRaw('SUM(COALESCE(admin_fee, 0)) as total_admin_fee')
            ->selectRaw('SUM(COALESCE(agent_fee, 0)) as total_agent_fee')
            ->selectRaw('SUM(COALESCE(cashback_fee, 0)) as total_cashback_fee')
            ->selectRaw('COUNT(*) as total_transactions')
            ->whereNotNull($groupColumn)
            ->whereNotNull('trx_type')
            ->groupBy($groupColumn);

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
        $headers = ['Model Type', 'Model ID', 'Type', 'Total Transactions', 'Total Amount'];
        $rows = [];

        // Preview transaction type rows
        foreach ($this->statDimensions() as $dimension) {
            $this->buildAggregationQuery($statusFilter, $dimension['column'])
                ->orderBy($dimension['column'])
                ->chunk($chunkSize, function (Collection $chunk) use (&$rows, $dimension): void {
                    foreach ($chunk as $row) {
                        $trxType = TrxType::tryFrom((string) $row->trx_type);
                        
                        if (! $trxType) {
                            continue;
                        }

                        // Add transaction type row
                        $rows[] = [
                            $dimension['label'],
                            (int) $row->model_id,
                            $trxType->value,
                            (int) $row->total_transactions,
                            $row->total_net_amount,
                        ];
                    }
                });
        }

        // Preview fee type rows
        foreach ($this->statDimensions() as $dimension) {
            $this->buildFeeAggregationQuery($statusFilter, $dimension['column'])
                ->orderBy($dimension['column'])
                ->chunk($chunkSize, function (Collection $chunk) use (&$rows, $dimension): void {
                    foreach ($chunk as $row) {
                        $feeTypes = [
                            'mdr_fee' => $row->total_mdr_fee,
                            'admin_fee' => $row->total_admin_fee,
                            'agent_fee' => $row->total_agent_fee,
                            'cashback_fee' => $row->total_cashback_fee,
                        ];

                        foreach ($feeTypes as $feeType => $feeAmount) {
                            if ($feeAmount > 0) {
                                $rows[] = [
                                    $dimension['label'],
                                    (int) $row->model_id,
                                    $feeType,
                                    (int) $row->total_transactions,
                                    $feeAmount,
                                ];
                            }
                        }
                    }
                });
        }

        $this->table($headers, $rows);
    }
}
