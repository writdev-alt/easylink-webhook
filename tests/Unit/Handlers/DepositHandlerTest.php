<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers;

use App\Models\Transaction;
use App\Services\Handlers\DepositHandler;
use App\Services\WalletService;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DepositHandler::class)]
class DepositHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        app()->forgetInstance(WalletService::class);

        parent::tearDown();
    }

    public function test_handle_success_adds_money_to_wallet(): void
    {
        $handler = new DepositHandler();

        $transaction = new Transaction([
            'wallet_reference' => 'wallet-uuid',
            'net_amount' => 1000.0,
        ]);

        $mock = Mockery::mock(WalletService::class);
        $mock->shouldReceive('addMoneyByWalletUuid')
            ->once()
            ->with('wallet-uuid', 1000.0);

        app()->instance(WalletService::class, $mock);

        $handler->handleSuccess($transaction);

        $this->addToAssertionCount(1);
    }

    public function test_handle_fail_is_noop(): void
    {
        $handler = new DepositHandler();

        $transaction = new Transaction();

        $this->assertNull($handler->handleFail($transaction));
    }

    public function test_handle_submitted_is_noop(): void
    {
        $handler = new DepositHandler();

        $transaction = new Transaction();

        $this->assertNull($handler->handleSubmitted($transaction));
    }
}


