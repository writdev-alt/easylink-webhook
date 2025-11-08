<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TrxStatus;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FailStaleTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:fail-stale
                            {--hours=24 : Age threshold in hours before a transaction is failed}
                            {--chunk=100 : Number of transactions processed per chunk}
                            {--dry-run : Only display how many transactions would be updated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark stale transactions older than the configured threshold as failed.';

    /**
     * Execute the console command.
     */
    public function handle(TransactionService $transactionService): int
    {
        $hours = $this->validatedPositiveIntegerOption('hours');
        $chunkSize = $this->validatedPositiveIntegerOption('chunk');
        $dryRun = (bool) $this->option('dry-run');

        $threshold = Carbon::now()->subHours($hours);
        $statusList = $this->pendingStatuses();
        $statusLabels = implode(', ', array_map(static fn (TrxStatus $status) => $status->value, $statusList));

        $this->info(sprintf(
            'Scanning for transactions created on or before %s with statuses [%s].',
            $threshold->toDateTimeString(),
            $statusLabels
        ));

        $affected = 0;

        Transaction::query()
            ->whereIn('status', $statusList)
            ->where('created_at', '<=', $threshold)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($transactions) use (&$affected, $dryRun, $transactionService, $hours): void {
                foreach ($transactions as $transaction) {
                    /** @var \App\Models\Transaction $transaction */
                    if ($dryRun) {
                        $affected++;

                        continue;
                    }

                    $transactionService->failTransaction(
                        $transaction->trx_id,
                        'Automatically marked as failed after exceeding the pending timeout.',
                        sprintf('Transaction exceeded the pending timeout threshold of %d hours.', $hours)
                    );

                    $affected++;
                }
            }, 'id');

        if ($dryRun) {
            $this->info(sprintf('%d transactions would be marked as failed.', $affected));

            return self::SUCCESS;
        }

        $this->info(sprintf('%d transactions were marked as failed.', $affected));

        return self::SUCCESS;
    }

    /**
     * @return array<int, TrxStatus>
     */
    private function pendingStatuses(): array
    {
        return [
            TrxStatus::PENDING,
            TrxStatus::AWAITING_FI_PROCESS,
            TrxStatus::AWAITING_PG_PROCESS,
            TrxStatus::AWAITING_USER_ACTION,
            TrxStatus::AWAITING_ADMIN_APPROVAL,
        ];
    }

    private function validatedPositiveIntegerOption(string $name): int
    {
        $value = (int) $this->option($name);

        if ($value <= 0) {
            $this->warn(sprintf('Option "--%s" must be a positive integer. Falling back to 1.', $name));

            return 1;
        }

        return $value;
    }
}
