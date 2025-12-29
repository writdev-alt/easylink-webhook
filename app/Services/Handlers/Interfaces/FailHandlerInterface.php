<?php

namespace App\Services\Handlers\Interfaces;

use Wrpay\Core\Models\Transaction;

interface FailHandlerInterface
{
    public function handleFail(Transaction $transaction): bool;
}
