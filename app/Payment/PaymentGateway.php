<?php

namespace App\Payment;

use Illuminate\Http\Request;

interface PaymentGateway
{
    public function handleIPN(Request $request);
}
