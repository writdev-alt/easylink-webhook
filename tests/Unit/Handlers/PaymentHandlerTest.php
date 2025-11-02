<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers;

use App\Enums\AmountFlow;
use App\Enums\TrxType;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Handlers\PaymentHandler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

        Schema::dropIfExists('wallets');

        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('uuid')->unique();
            $table->decimal('balance', 18, 2)->default(0);
            $table->decimal('balance_sandbox', 18, 2)->default(0);
            $table->decimal('hold_balance', 18, 2)->default(0);
            $table->decimal('hold_balance_sandbox', 18, 2)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('wallets');

        Mockery::close();

        parent::tearDown();
    }

    public function test_handle_success_subtracts_money_for_minus_flow(): void
    {
        $uuid = (string) Str::uuid();
        $wallet = Wallet::create([
            'uuid' => $uuid,
            'balance' => 1000,
            'hold_balance' => 0,
            'status' => true,
        ]);

        $transaction = new Transaction([
            'wallet_reference' => $uuid,
            'amount_flow' => AmountFlow::MINUS,
            'payable_amount' => 500.0,
            'trx_type' => TrxType::PAYMENT,
        ]);

        $handler = new PaymentHandler();
        $handler->handleSuccess($transaction);

        $this->assertEquals(500.0, $wallet->fresh()->getActualBalance());
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

        $handler = new PaymentHandler();
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

        $handler = new PaymentHandler();
        $handler->handleSuccess($transaction);

        $this->assertEquals(300.0, $wallet->fresh()->getActualBalance());
    }
}


