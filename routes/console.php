<?php

use App\Console\Commands\FailStaleTransactionsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(FailStaleTransactionsCommand::class)
    ->everyThirtyMinutes()
    ->description('Mark stale transactions as failed after exceeding the pending timeout.');
