<?php

use Illuminate\Support\Facades\Route;

Route::post('/{gateway}/{action?}', [\App\Http\Controllers\IPNController::class, 'handleIPN'])->name('ipn.handle');
