<?php

use Illuminate\Support\Facades\Route;

Route::post('/{gateway}/{action?}', [\App\Http\Controllers\IPNController::class, 'handleIPN'])->name('ipn.handle');

Route::post('/webhooks/{trx_id}/resend', [\App\Http\Controllers\WebhookController::class, 'resend'])->name('webhooks.resend');
