<?php

namespace App\Payment;

use App\Payment\Easylink\EasylinkPaymentGateway;
use App\Payment\Netzme\NetzmePaymentGateway;
use Exception;
use Illuminate\Support\Facades\App;

class PaymentGatewayFactory
{
    /**
     * Create an instance of a payment gateway.
     *
     *
     * @throws Exception
     */
    public function getGateway(?string $gatewayCode)
    {
        if ($gatewayCode === null) {
            throw new Exception('Unsupported payment gateway: null');
        }

        return match ($gatewayCode) {
            'netzme' => App::make(NetzmePaymentGateway::class),
            'easylink' => App::make(EasylinkPaymentGateway::class),
            default => throw new Exception(sprintf('Unsupported payment gateway: %s', $gatewayCode)),
        };
    }
}
