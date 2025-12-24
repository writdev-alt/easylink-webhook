<?php

namespace App\Services\Handlers\Interfaces;

use Wrpay\Core\Models\Transaction;

interface SuccessHandlerInterface
{
    public function handleSuccess(Transaction $transaction): bool;
}
