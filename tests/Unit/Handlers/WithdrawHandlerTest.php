<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers;

use App\Models\Transaction;
use App\Payment\PaymentGatewayFactory;
use App\Services\Handlers\WithdrawHandler;
use App\Services\WalletService;
use App\Services\WebhookService;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(WithdrawHandler::class)]
class WithdrawHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        app()->forgetInstance(WebhookService::class);
        app()->forgetInstance(WalletService::class);
        app()->forgetInstance(PaymentGatewayFactory::class);

        parent::tearDown();
    }

    public function test_handle_fail_triggers_withdrawal_webhook(): void
    {
        $transaction = new Transaction([
            'trx_id' => 'TRX-2001',
            'wallet_reference' => 'wallet-xyz',
            'payable_amount' => 500.0,
        ]);

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('subtractMoneyByWalletUuid')
            ->once()
            ->with('wallet-xyz', 500.0);
        app()->instance(WalletService::class, $walletService);

        $webhookMock = Mockery::mock(WebhookService::class);
        $webhookMock->shouldReceive('sendWithdrawalWebhook')
            ->once()
            ->with($transaction, 'withdrawal failed');

        app()->instance(WebhookService::class, $webhookMock);

        $handler = new WithdrawHandler;
        $handler->handleFail($transaction);

        $this->addToAssertionCount(1);
    }

    public function test_handle_submitted_triggers_withdrawal_webhook(): void
    {
        $transaction = new Transaction([
            'trx_id' => 'TRX-2002',
        ]);

        $webhookMock = Mockery::mock(WebhookService::class);
        $webhookMock->shouldReceive('sendWithdrawalWebhook')
            ->once()
            ->with($transaction, 'withdrawal submitted');

        app()->instance(WebhookService::class, $webhookMock);

        $handler = new WithdrawHandler;
        $handler->handleSubmitted($transaction);

        $this->addToAssertionCount(1);
    }

    public function test_handle_success_updates_transaction_and_notifies_services(): void
    {
        $trxID = 'TRX-3001';
        $transaction = new class([
            'trx_id' => $trxID,
            'wallet_reference' => 'wallet-xyz',
            'payable_amount' => 750.0, 
            'trx_data' => [
                // Provide top-level reference expected by handler
                'reference' => 'REF-123',
            ]
             ]) extends Transaction
        {
            public array $updates = [];

            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
            }

            public function update(array $attributes = [], array $options = [])
            {
                $this->updates[] = $attributes;
                $this->fill($attributes);

                return true;
            }
        };

        // Removed debug dump to allow test to run

        $gateway = Mockery::mock();
        $disbursementPayload = $this->mockDomesticDisbursementPayload();

        $gateway->shouldReceive('getDomesticTransfer')
            ->once()
            ->with('REF-123')
            ->andReturn($disbursementPayload);

        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('getGateway')
            ->once()
            ->with('easylink')
            ->andReturn($gateway);
        app()->instance(PaymentGatewayFactory::class, $gatewayFactory);

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('subtractMoneyByWalletUuid')
            ->once()
            ->with('wallet-xyz', 750.0);
        app()->instance(WalletService::class, $walletService);

        $webhookMock = Mockery::mock(WebhookService::class);
        $webhookMock->shouldReceive('sendWithdrawalWebhook')
            ->once()
            ->with($transaction, 'withdrawal completed');
        app()->instance(WebhookService::class, $webhookMock);

        $handler = new WithdrawHandler;
        $handler->handleSuccess($transaction);

        $expectedTrxData = array_merge(['reference' => 'REF-123'], $disbursementPayload);

        $this->assertSame($expectedTrxData, $transaction->trx_data);

        $this->addToAssertionCount(1);
    }

    private function mockDomesticDisbursementPayload(): array
    {
        return [
            'disbursement_id' => '202510162200620000082571',
            'reference_id' => 'TRX-2025.10.30-BROIP4S4RU',
            'remittance_type' => 'domestic',
            'source_country' => 'IDN',
            'source_currency' => 'IDR',
            'destination_country' => 'IDN',
            'destination_currency' => 'IDR',
            'source_amount' => '100123.000000000000000000',
            'destination_amount' => '100123.000000000000000000',
            'fee' => '3500.000000000000000000',
            'state' => '7',
            'state_change_time' => '2025-10-16T15:33:02+07:00',
            'reason' => '',
        ];
    }
}
