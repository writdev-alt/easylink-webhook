<?php

namespace App\Console\Commands;

use App\Jobs\UpdateTransactionStatJob;
use App\Models\Transaction;
use Illuminate\Console\Command;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Test command executed successfully.');
        $transaction = Transaction::first();

        if ($transaction) {
            UpdateTransactionStatJob::dispatch($transaction);
            $this->info('Dispatched UpdateTransactionStatJob for transaction ID '.$transaction->id);
        } else {
            $this->warn('No transaction found to dispatch UpdateTransactionStatJob.');
        }

        //        $web =  app(\App\Services\WebhookService::class)->sendWithdrawalWebhook($transaction, '1234567890');

    }
}
