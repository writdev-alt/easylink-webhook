<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers;

use App\Enums\AmountFlow;
use App\Enums\TrxType;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Handlers\PaymentHandler;
use App\Services\WalletService;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(PaymentHandler::class)]
class PaymentHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        app()->forgetInstance(WebhookService::class);
        app()->forgetInstance(WalletService::class);

        parent::tearDown();
    }

    public function test_handle_success_adds_hold_for_receive_payment(): void
    {
        $uuid = (string) Str::uuid();
        $wallet = Wallet::create([
            'uuid' => $uuid,
            'balance' => 0,
            'hold_balance' => 0,
            'status' => true,
        ]);

        $transaction = new Transaction([
            'wallet_reference' => $uuid,
            'amount_flow' => AmountFlow::PLUS,
            'net_amount' => 700.0,
            'trx_type' => TrxType::RECEIVE_PAYMENT,
        ]);

        $handler = new PaymentHandler;
        $handler->handleSuccess($transaction);

        $fresh = $wallet->fresh();
        $this->assertEquals(700.0, $fresh->getActualBalance());
        $this->assertEquals(700.0, $fresh->getActualHoldBalance());
    }

    public function test_handle_success_adds_money_for_plus_flow_non_receive(): void
    {
        $uuid = (string) Str::uuid();
        $wallet = Wallet::create([
            'uuid' => $uuid,
            'balance' => 0,
            'hold_balance' => 0,
            'status' => true,
        ]);

        $transaction = new Transaction([
            'wallet_reference' => $uuid,
            'amount_flow' => AmountFlow::PLUS,
            'net_amount' => 300.0,
            'trx_type' => TrxType::DEPOSIT,
        ]);

        $handler = new PaymentHandler;
        $handler->handleSuccess($transaction);

        $this->assertEquals(300.0, $wallet->fresh()->getActualBalance());
    }

    public function test_handle_success_sends_payment_receive_webhook(): void
    {
        $uuid = (string) Str::uuid();
        $wallet = Wallet::create([
            'uuid' => $uuid,
            'balance' => 0,
            'hold_balance' => 0,
            'status' => true,
        ]);

        $transaction = new Transaction([
            'wallet_reference' => $uuid,
            'amount_flow' => AmountFlow::PLUS,
            'net_amount' => 300.0,
            'trx_type' => TrxType::DEPOSIT,
        ]);

        $walletServiceMock = Mockery::mock(WalletService::class);
        $walletServiceMock->shouldReceive('addMoneyByWalletUuid')
            ->once()
            ->with($uuid, 300.0)
            ->andReturn($wallet);
        app()->instance(WalletService::class, $walletServiceMock);

        $webhookServiceMock = Mockery::mock(WebhookService::class);
        $webhookServiceMock->shouldReceive('sendPaymentReceiveWebhook')
            ->once()
            ->with($transaction, 'Payment received')
            ->andReturn(true);
        app()->instance(WebhookService::class, $webhookServiceMock);

        $handler = new PaymentHandler;
        $handler->handleSuccess($transaction);

        $this->addToAssertionCount(1);
    }
}
