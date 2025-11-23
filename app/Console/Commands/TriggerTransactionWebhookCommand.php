<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TrxType;
use App\Models\Transaction;
use App\Services\WebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class TriggerTransactionWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:trigger
                            {trx_id : Transaction trx_id}
                            {--message= : Optional custom message for webhook payload}
                            {--rrn= : Optional RRN value required for receive payment transactions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a webhook for a given transaction and record diagnostic logs.';

    /**
     * Execute the console command.
     */
    public function handle(WebhookService $webhookService): int
    {
        $trxId = (string) $this->argument('trx_id');

        /** @var Transaction|null $transaction */
        $transaction = Transaction::query()->where('trx_id', $trxId)->first();

        if (! $transaction) {
            $this->error(sprintf('Transaction not found for trx_id %s', $trxId));

            return self::FAILURE;
        }

        $message = $this->option('message');
        $rrn = $this->option('rrn') ?: Arr::get($transaction->trx_data ?? [], 'rrn');

        Log::info('Console webhook trigger initiated', [
            'trx_id' => $transaction->trx_id,
            'trx_type' => $transaction->trx_type->value,
            'triggered_by' => 'console',
        ]);

        try {
            $sent = match ($transaction->trx_type) {
                TrxType::RECEIVE_PAYMENT => $this->triggerReceivePaymentWebhook($webhookService, $transaction, $rrn, $message),
                TrxType::WITHDRAW => $webhookService->sendWithdrawalWebhook($transaction, $message),
                default => $webhookService->sendGenericWebhook($transaction, $message),
            };
        } catch (\Throwable $exception) {
            Log::error('Console webhook trigger failed', [
                'trx_id' => $transaction->trx_id,
                'trx_type' => $transaction->trx_type->value,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            $this->error(sprintf('Webhook dispatch failed: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        if ($sent) {
            $this->info('Webhook dispatched successfully.');

            return self::SUCCESS;
        }

        $this->warn('Webhook dispatch skipped or failed. Check logs for details.');

        return self::FAILURE;
    }

    private function triggerReceivePaymentWebhook(WebhookService $webhookService, Transaction $transaction, ?string $rrn, ?string $message): bool
    {
        if ($rrn === null) {
            $this->error('RRN is required for receive payment transactions. Provide via --rrn option or trx_data[rrn].');

            return false;
        }

        return $webhookService->sendPaymentReceiveWebhook($transaction, $rrn, $message);
    }
}
