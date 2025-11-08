<?php

namespace App\Jobs;

use App\Enums\TrxType;
use App\Models\TransactionStat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateTransactionStatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function __construct(public Model $model, public int|float $amount, public TrxType $type) {}

    public function handle(): void
    {
        $match = [
            'model_id' => $this->model->getKey(),
            'model_type' => $this->model->getMorphClass(),
            'type' => $this->type->value,
        ];

        $stat = TransactionStat::firstOrCreate($match, [
            'total_transactions' => 0,
            'total_amount' => 0,
        ]);

        TransactionStat::whereKey($stat->getKey())->update([
            'total_transactions' => DB::raw('total_transactions + 1'),
            'total_amount' => DB::raw('total_amount + '.(int) $this->amount),
            'updated_at' => now(),
        ]);
    }
}
