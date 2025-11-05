<?php

namespace App\Console\Commands;

use App\Enums\TrxStatus;
use App\Models\Transaction;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BulkIndexTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:bulk-index 
                            {--index=transactions : The Elasticsearch index name}
                            {--limit= : Limit the number of transactions to process}
                            {--date-from= : Start date for filtering transactions (Y-m-d)}
                            {--date-to= : End date for filtering transactions (Y-m-d)}
                            {--chunk-size=500 : Number of transactions to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk index transactions to Elasticsearch';

    /**
     * Execute the console command.
     */
    public function handle(ElasticsearchService $elasticsearchService): int
    {
        $indexName = $this->option('index');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dateFrom = $this->option('date-from') ? Carbon::parse($this->option('date-from')) : null;
        $dateTo = $this->option('date-to') ? Carbon::parse($this->option('date-to')) : null;
        $chunkSize = (int) $this->option('chunk-size');

        $this->info('Starting bulk index operation for transactions...');
        $this->info("Index: {$indexName}");
        $this->info("Chunk size: {$chunkSize}");

        // Delete index if it exists
        // try {
        //     if ($elasticsearchService->indexExists($indexName)) {
        //         $this->warn("Index '{$indexName}' exists. Deleting it...");
        //         $elasticsearchService->deleteIndex($indexName);
        //         $this->info("Index '{$indexName}' deleted successfully.");
        //     }
        // } catch (\Exception $e) {
        //     $this->error("Failed to delete index: " . $e->getMessage());
        //     return Command::FAILURE;
        // }

        // Build query
        $query = Transaction::query()->whereStatus(TrxStatus::COMPLETED);

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom->startOfDay());
            $this->info("Date from: {$dateFrom->format('Y-m-d')}");
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo->endOfDay());
            $this->info("Date to: {$dateTo->format('Y-m-d')}");
        }

        if ($limit) {
            $query->limit($limit);
            $this->info("Limit: {$limit}");
        }

        $totalTransactions = $query->count();
        $this->info("Total transactions to process: {$totalTransactions}");

        if ($totalTransactions === 0) {
            $this->warn('No transactions found to index.');

            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($totalTransactions);
        $progressBar->start();

        $processed = 0;
        $errors = 0;

        try {
            $query->chunk($chunkSize, function ($transactions) use ($elasticsearchService, $indexName, &$processed, &$errors, $progressBar) {
                try {
                    $data = $transactions->map(function ($transaction) {
                        return $this->transformTransaction($transaction);
                    })->toArray();

                    $elasticsearchService->bulkIndexData($indexName, $data);
                    $processed += count($data);
                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error('Error processing chunk: '.$e->getMessage());
                }

                $progressBar->advance(count($transactions));
            });

            $progressBar->finish();
            $this->newLine(2);

            $this->info('Bulk indexing completed!');
            $this->info("Successfully processed: {$processed} transactions");
            if ($errors > 0) {
                $this->warn("Errors encountered: {$errors} chunks");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error('Bulk indexing failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Transform a transaction model to Elasticsearch document format.
     */
    protected function transformTransaction(Transaction $transaction): array
    {
        return [
            'id' => (string) $transaction->id,
            'trx_id' => $transaction->trx_id,
            'merchant_id' => $transaction->merchant_id,
            'user_id' => $transaction->user_id,
            'customer_id' => $transaction->customer_id,
            'trx_type' => $transaction->trx_type?->value,
            'description' => $transaction->description,
            'provider' => $transaction->provider,
            'method_id' => $transaction->method_id,
            'method_type' => $transaction->method_type,
            'processing_type' => $transaction->processing_type?->value,
            'amount' => $transaction->amount,
            'amount_flow' => $transaction->amount_flow?->value,
            'ma_fee' => $transaction->ma_fee,
            'mdr_fee' => $transaction->mdr_fee,
            'admin_fee' => $transaction->admin_fee,
            'agent_fee' => $transaction->agent_fee,
            'cashback_fee' => $transaction->cashback_fee,
            'trx_fee' => $transaction->trx_fee,
            'currency' => $transaction->currency,
            'net_amount' => $transaction->net_amount,
            'payable_amount' => $transaction->payable_amount,
            'payable_currency' => $transaction->payable_currency,
            'wallet_reference' => $transaction->wallet_reference,
            'trx_reference' => $transaction->trx_reference,
            'trx_data' => $transaction->trx_data,
            'remarks' => $transaction->remarks,
            'status' => $transaction->status?->value,
            'rrn' => $transaction->trx_data['additionalInfo']['rrn'] ?? null,
            'released_at' => $transaction->released_at?->toIso8601String(),
            'created_at' => $transaction->created_at?->toIso8601String(),
            'updated_at' => $transaction->updated_at?->toIso8601String(),
            'deleted_at' => $transaction->deleted_at?->toIso8601String(),
        ];
    }
}
