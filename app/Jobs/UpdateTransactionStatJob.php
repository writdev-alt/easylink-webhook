<?php

namespace App\Jobs;

use App\Models\DailyTransactionSummary;
use App\Models\TransactionStat;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Wrpay\Core\Enums\AmountFlow;
use Wrpay\Core\Enums\TrxType;
use Wrpay\Core\Models\Merchant;
use Wrpay\Core\Models\Transaction;

class UpdateTransactionStatJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function __construct(
        public Transaction $transaction,
    ) {}

    public function handle(): void
    {
        $trxType = TrxType::tryFrom($this->transaction->trx_type->value);

        if (! $trxType) {
            return;
        }

        $amount = (float) ($this->transaction->payable_amount ?? 0);

        $this->updateStats($trxType, $amount);
        $this->updateDailySummary($amount, $trxType);
    }

    /**
     * Increment statistics for users/merchants and fees.
     */
    protected function updateStats(TrxType $trxType, float $amount): void
    {
        $this->incrementStat(
            $this->transaction->user_id,
            User::class,
            $trxType->value,
            (int) $amount
        );

        if ($this->transaction->merchant_id) {
            $this->incrementStat(
                (int) $this->transaction->merchant_id,
                Merchant::class,
                $trxType->value,
                (int) $amount
            );
        }

        $fees = [
            'mdr_fee' => (float) ($this->transaction->mdr_fee ?? 0),
            'admin_fee' => (float) ($this->transaction->admin_fee ?? 0),
            'agent_fee' => (float) ($this->transaction->agent_fee ?? 0),
            'cashback_fee' => (float) ($this->transaction->cashback_fee ?? 0),
        ];

        foreach ($fees as $feeType => $feeAmount) {
            if ($feeAmount <= 0) {
                continue;
            }

            $this->incrementStat(
                $this->transaction->user_id,
                User::class,
                $feeType,
                (int) $feeAmount
            );

            if ($this->transaction->merchant_id) {
                $this->incrementStat(
                    (int) $this->transaction->merchant_id,
                    Merchant::class,
                    $feeType,
                    (int) $feeAmount
                );
            }
        }
    }

    /**
     * Create or increment a stat row for the given model/type.
     */
    protected function incrementStat(?int $modelId, string $modelClass, string $type, int $amount): void
    {
        if (! $modelId) {
            return;
        }

        $updated = TransactionStat::query()
            ->where('model_id', $modelId)
            ->where('model_type', $modelClass)
            ->where('type', $type)
            ->update([
                'total_transactions' => DB::raw('total_transactions + 1'),
                'total_amount' => DB::raw('total_amount + '.$amount),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            TransactionStat::query()->create([
                'model_id' => $modelId,
                'model_type' => $modelClass,
                'type' => $type,
                'total_transactions' => 1,
                'total_amount' => $amount,
            ]);
        }
    }

    /**
     * Update daily incoming/withdraw summary for the related user.
     */
    protected function updateDailySummary(float $amount, TrxType $type): void
    {
        $userId = $this->resolveUserId();

        if (! $userId) {
            return;
        }

        $date = now()->toDateString();

        $summary = DailyTransactionSummary::firstOrCreate(
            ['date' => $date, 'user_id' => $userId],
            [
                'total_incoming' => 0,
                'count_incoming' => 0,
                'total_withdraw' => 0,
                'count_withdraw' => 0,
            ],
        );

        $updates = ['updated_at' => now()];
        $flow = $type->cashFlow();

        if ($flow === AmountFlow::PLUS) {
            $updates['total_incoming'] = DB::raw('total_incoming + '.(float) $amount);
            $updates['count_incoming'] = DB::raw('count_incoming + 1');
        } elseif ($flow === AmountFlow::MINUS) {
            $updates['total_withdraw'] = DB::raw('total_withdraw + '.(float) $amount);
            $updates['count_withdraw'] = DB::raw('count_withdraw + 1');
        } else {
            return;
        }

        DailyTransactionSummary::where('date', $summary->date)
            ->where('user_id', $summary->user_id)
            ->update($updates);
    }

    /**
     * Resolve the user id from the provided transaction.
     */
    protected function resolveUserId(): ?int
    {
        return $this->transaction->user_id ? (int) $this->transaction->user_id : null;
    }
}
