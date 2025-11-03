<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Handlers\DepositHandler;
use App\Services\WalletService;
use App\Services\WebhookService;
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
        app()->forgetInstance(WebhookService::class);

        parent::tearDown();
    }

    public function test_handle_success_adds_money_to_wallet(): void
    {
        $handler = new DepositHandler;

        $transaction = new Transaction([
            'wallet_reference' => 'wallet-uuid',
            'net_amount' => 1000.0,
        ]);

        $wallet = Mockery::mock(Wallet::class);

        $walletServiceMock = Mockery::mock(WalletService::class);
        $walletServiceMock->shouldReceive('addMoneyByWalletUuid')
            ->once()
            ->with('wallet-uuid', 1000.0)
            ->andReturn($wallet);

        app()->instance(WalletService::class, $walletServiceMock);

        $webhookServiceMock = Mockery::mock(WebhookService::class);
        $webhookServiceMock->shouldReceive('sendPaymentReceiveWebhook')
            ->once()
            ->with($transaction, 'Deposit Completed')
            ->andReturn(true);

        app()->instance(WebhookService::class, $webhookServiceMock);

        $result = $handler->handleSuccess($transaction);

        $this->assertTrue($result);
    }
}
