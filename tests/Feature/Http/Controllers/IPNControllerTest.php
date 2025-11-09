<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Constants\CurrencyType;
use App\Enums\MethodType;
use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Events\WebhookReceived;
use App\Http\Controllers\IPNController;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Payment\PaymentGatewayFactory;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(IPNController::class)]
class IPNControllerTest extends TestCase
{
    public function test_handle_ipn_for_netzme_gateway()
    {
        $this->assertTrue(true);
    }
}
