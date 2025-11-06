<?php

namespace App\Listeners;

use App\Events\GenerateStatisticEvent;
use App\Models\TransactionStat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GenerateStatisticListener implements ShouldQueue
{
    use InteractsWithQueue;
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(GenerateStatisticEvent $event): void
    {
        TransactionStat::where('merchant_id', $event->merchantId)
            ->where('trx_type', $event->type)
            ->increment('total_transactions', $event->amount);
    }
}
