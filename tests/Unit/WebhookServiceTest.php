<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Models\Transaction;
use App\Services\WebhookService;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_send_webhook_returns_false_when_disabled(): void
    {
        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->setAttribute('trx_id', 'TRX-disabled');
        $transaction->setAttribute('trx_type', TrxType::RECEIVE_PAYMENT);
        $transaction->shouldReceive('isWebhookEnabled')->once()->andReturn(false);

        $service = new WebhookService();

        $this->assertFalse($service->sendWebhook($transaction));
    }

    public function test_send_webhook_returns_false_when_already_sent(): void
    {
        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->setAttribute('trx_id', 'TRX-already');
        $transaction->setAttribute('trx_type', TrxType::WITHDRAW);
        $transaction->shouldReceive('isWebhookEnabled')->once()->andReturn(true);
        $transaction->shouldReceive('alreadySentAutomaticWebhook')->once()->andReturn(true);

        $service = new WebhookService();

        $this->assertFalse($service->sendWebhook($transaction));
    }

    public function test_send_webhook_routes_withdraw_transactions(): void
    {
        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->setAttribute('trx_id', 'TRX-withdraw');
        $transaction->setAttribute('trx_type', TrxType::WITHDRAW);
        $transaction->shouldReceive('isWebhookEnabled')->andReturn(true);
        $transaction->shouldReceive('alreadySentAutomaticWebhook')->andReturn(false);

        $service = Mockery::mock(WebhookService::class)->makePartial();
        $service->shouldReceive('sendWithdrawalWebhook')
            ->once()
            ->with($transaction, null)
            ->andReturn(true);

        $this->assertTrue($service->sendWebhook($transaction));
    }

    public function test_send_payment_receive_webhook_dispatches_with_valid_configuration(): void
    {
        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->setAttribute('trx_id', 'TRX123');
        $transaction->setAttribute('trx_type', TrxType::RECEIVE_PAYMENT);
        $transaction->setAttribute('status', TrxStatus::COMPLETED);
        $transaction->setAttribute('payable_amount', 10000.0);
        $transaction->setAttribute('net_amount', 9500.0);
        $transaction->setAttribute('payable_currency', 'IDR');
        $transaction->setAttribute('description', 'Payment for order #1');
        $transaction->setAttribute('trx_reference', 'REF123');
        $transaction->setAttribute('provider', 'qris');
        $transaction->setAttribute('mdr_fee', 300.0);
        $transaction->setAttribute('admin_fee', 200.0);
        $transaction->setAttribute('agent_fee', 0.0);
        $transaction->setAttribute('trx_fee', 500.0);
        $transaction->setAttribute('updated_at', Carbon::now());
        $transaction->setAttribute('merchant_id', 10);
        $transaction->setAttribute('trx_data', [
            'success_redirect' => 'https://merchant.test/success',
            'cancel_redirect' => 'https://merchant.test/cancel',
        ]);
        $transaction->setRelation('customer', (object) ['name' => 'Alice', 'email' => 'alice@example.com', 'phone' => '0800000000']);
        $transaction->setRelation('merchant', (object) ['business_name' => 'Demo Store']);

        $transaction->shouldReceive('getWebhookConfig')->andReturn([
            'url' => 'https://merchant.test/webhook',
            'secret' => 'secret-key',
            'verify_ssl' => false,
        ]);
        $transaction->shouldReceive('setWebhookCall')->once();

        $service = Mockery::mock(WebhookService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('dispatchWebhook')
            ->once()
            ->withArgs(function (string $url, string $secret, array $payload, $trx) use ($transaction) {
                $this->assertSame('https://merchant.test/webhook', $url);
                $this->assertSame('secret-key', $secret);
                $this->assertSame($transaction, $trx);
                $this->assertEquals('receive_payment', $payload['event']);
                $this->assertEquals('Payment Completed', $payload['message']);

                return true;
            });

        $result = $service->sendPaymentReceiveWebhook($transaction, 'Payment Completed');

        $this->assertTrue($result);
    }

    public function test_send_payment_receive_webhook_returns_false_when_config_missing(): void
    {
        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->setAttribute('trx_id', 'TRX123');
        $transaction->setAttribute('trx_type', TrxType::RECEIVE_PAYMENT);
        $transaction->shouldReceive('getWebhookConfig')->andReturn([
            'url' => null,
            'secret' => null,
        ]);

        $service = Mockery::mock(WebhookService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldNotReceive('dispatchWebhook');

        $this->assertFalse($service->sendPaymentReceiveWebhook($transaction));
    }
}


