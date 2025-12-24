<?php

namespace App\Services\Handlers\Interfaces;

use Wrpay\Core\Models\Transaction;

interface SubmittedHandlerInterface
{
    public function handleSubmitted(Transaction $transaction): bool;
}
