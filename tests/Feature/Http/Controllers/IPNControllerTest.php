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
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private const CURRENCY_ID = 360;

    private array $payload;

    private array $pendingPayload;

    private array $easylinkPayloads = [];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        DB::table('currencies')->insertOrIgnore([
            'id' => self::CURRENCY_ID,
            'flag' => null,
            'name' => 'Indonesian Rupiah',
            'code' => 'IDR',
            'symbol' => 'Rp',
            'type' => CurrencyType::FIAT,
            'auto_wallet' => 0,
            'exchange_rate' => 1,
            'rate_live' => false,
            'default' => 'inactive',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->payload = json_decode(
            (string) file_get_contents(base_path('tests/mockups/netzme/success.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->pendingPayload = json_decode(
            (string) file_get_contents(base_path('tests/mockups/netzme/pending.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        // Load all easylink transfer state payloads
        $easylinkStates = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 26, 27];
        foreach ($easylinkStates as $state) {
            $filename = match ($state) {
                10 => 'transfer-state-10-refund_success.json',
                26 => 'transfer-state-26-processing_bank_partner.json',
                27 => 'transfer-state-27-remind_recipient.json',
                default => "transfer-state-0{$state}.json",
            };

            $filePath = base_path("tests/mockups/easylink/{$filename}");
            if (file_exists($filePath)) {
                $this->easylinkPayloads[$state] = json_decode(
                    (string) file_get_contents($filePath),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
        }
    }

    public function test_handle_ipn_returns_error_for_unsupported_gateway(): void
    {
        Event::fake([WebhookReceived::class]);

        $factory = Mockery::mock(PaymentGatewayFactory::class);
        $factory->shouldReceive('getGateway')
            ->once()
            ->with('invalid')
            ->andThrow(new \Exception('Unsupported payment gateway: invalid'));

        $controller = new IPNController($factory);

        $request = $this->createJsonRequest($this->payload, gatewayPath: 'invalid');

        $response = $controller->handleIPN($request, 'invalid');

        $this->assertSame(404, $response->status());
        $this->assertSame([
            'status' => 'error',
            'message' => 'Unsupported payment gateway',
        ], $response->getData(true));

        Event::assertNotDispatched(WebhookReceived::class);
    }

    public function test_handle_ipn_invokes_default_handler_and_dispatches_event(): void
    {
        Event::fake([WebhookReceived::class]);

        $request = $this->createJsonRequest($this->payload);

        $gateway = Mockery::mock();
        $gateway->shouldReceive('handleIPN')
            ->once()
            ->with($request)
            ->andReturn('accepted');

        $factory = Mockery::mock(PaymentGatewayFactory::class);
        $factory->shouldReceive('getGateway')
            ->once()
            ->with('netzme')
            ->andReturn($gateway);

        $controller = new IPNController($factory);

        $response = $controller->handleIPN($request, 'netzme');

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'status' => 'success',
            'message' => 'Webhook received',
        ], $response->getData(true));

        Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $event) use ($request): bool {
            $this->assertSame('netzme', $event->gateway);
            $this->assertNull($event->action);
            $this->assertSame($request->fullUrl(), $event->url);
            $this->assertSame($request->headers->all(), $event->headers);
            $this->assertSame(array_merge($request->all(), ['_action' => null]), $event->payload);
            $this->assertSame('POST', $event->httpVerb);
            $this->assertSame($request->query->all(), $event->query);
            $this->assertSame($request->getContent(), $event->rawBody);

            return true;
        });
    }

    public function test_handle_ipn_invokes_action_specific_method(): void
    {
        Event::fake([WebhookReceived::class]);

        $request = $this->createJsonRequest($this->payload, query: ['foo' => 'bar']);

        $gateway = Mockery::mock();
        $gateway->shouldReceive('handleCallback')
            ->once()
            ->with($request)
            ->andReturn('queued');

        $factory = Mockery::mock(PaymentGatewayFactory::class);
        $factory->shouldReceive('getGateway')
            ->once()
            ->with('netzme')
            ->andReturn($gateway);

        $controller = new IPNController($factory);

        $response = $controller->handleIPN($request, 'netzme', 'callback');

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'status' => 'success',
            'message' => 'Webhook received',
        ], $response->getData(true));

        Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $event): bool {
            $this->assertSame('callback', $event->action);
            $this->assertSame('callback', $event->payload['_action']);

            return true;
        });
    }

    public function test_handle_ipn_success_updates_wallet_balance(): void
    {
        Event::fake([WebhookReceived::class]);

        $initialBalance = 1000.0;
        $netAmount = (int) $this->payload['netAmount']['value'];

        $wallet = Wallet::create([
            'uuid' => 'wallet-test-uuid',
            'user_id' => $this->user->id,
            'currency_id' => self::CURRENCY_ID,
            'balance' => $initialBalance,
            'balance_sandbox' => 0,
            'hold_balance' => 0,
            'hold_balance_sandbox' => 0,
            'status' => true,
        ])->refresh();

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'trx_id' => $this->payload['originalPartnerReferenceNo'],
            'trx_type' => TrxType::DEPOSIT,
            'status' => TrxStatus::PENDING,
            'net_amount' => $netAmount,
            'wallet_reference' => $wallet->uuid,
            'processing_type' => MethodType::SYSTEM,
            'amount' => $netAmount,
            'currency' => 'IDR',
            'payable_amount' => $netAmount,
            'trx_reference' => (string) Str::uuid(),
        ])->refresh();

        $request = $this->createJsonRequest($this->payload);

        $gateway = Mockery::mock();
        $gateway->shouldReceive('handleIPN')
            ->once()
            ->with($request)
            ->andReturnUsing(function (Request $request): string {
                app(TransactionService::class)->completeTransaction($request->input('originalPartnerReferenceNo'));

                return 'success';
            });

        $factory = Mockery::mock(PaymentGatewayFactory::class);
        $factory->shouldReceive('getGateway')
            ->once()
            ->with('netzme')
            ->andReturn($gateway);

        $controller = new IPNController($factory);

        $response = $controller->handleIPN($request, 'netzme');

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'status' => 'success',
            'message' => 'Webhook received',
        ], $response->getData(true));

        $this->assertSame($initialBalance + $netAmount, $wallet->fresh()->getActualBalance());
        $this->assertSame(TrxStatus::COMPLETED, $transaction->fresh()->status);
    }

    public function test_handle_ipn_pending_does_not_update_wallet_balance(): void
    {
        Event::fake([WebhookReceived::class]);

        $initialBalance = 1000.0;

        $wallet = Wallet::create([
            'uuid' => 'wallet-test-uuid',
            'user_id' => $this->user->id,
            'currency_id' => self::CURRENCY_ID,
            'balance' => $initialBalance,
            'balance_sandbox' => 0,
            'hold_balance' => 0,
            'hold_balance_sandbox' => 0,
            'status' => true,
        ])->refresh();

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'trx_id' => $this->pendingPayload['originalPartnerReferenceNo'],
            'trx_type' => TrxType::DEPOSIT,
            'status' => TrxStatus::PENDING,
            'net_amount' => (int) $this->pendingPayload['netAmount']['value'],
            'wallet_reference' => $wallet->uuid,
            'processing_type' => MethodType::SYSTEM,
            'amount' => (int) $this->pendingPayload['netAmount']['value'],
            'currency' => 'IDR',
            'payable_amount' => (int) $this->pendingPayload['netAmount']['value'],
            'trx_reference' => (string) Str::uuid(),
        ])->refresh();

        $request = $this->createJsonRequest($this->pendingPayload);

        $gateway = Mockery::mock();
        $gateway->shouldReceive('handleIPN')
            ->once()
            ->with($request)
            ->andReturn('pending');

        $factory = Mockery::mock(PaymentGatewayFactory::class);
        $factory->shouldReceive('getGateway')
            ->once()
            ->with('netzme')
            ->andReturn($gateway);

        $controller = new IPNController($factory);

        $response = $controller->handleIPN($request, 'netzme');

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'status' => 'success',
            'message' => 'Webhook received',
        ], $response->getData(true));

        $this->assertSame($initialBalance, $wallet->fresh()->getActualBalance());
        $this->assertSame(TrxStatus::PENDING, $transaction->fresh()->status);
    }

    public function test_handle_ipn_easylink_withdraw_complete_updates_wallet_balance(): void
    {
        Event::fake([WebhookReceived::class]);

        $initialBalance = 5000.0;
        if (! isset($this->easylinkPayloads[7])) {
            $this->markTestSkipped('Easylink state 7 payload not available');
        }

        $payload = $this->easylinkPayloads[7]; // State 7: COMPLETE
        $withdrawAmount = (float) $payload['source_amount'];
        $fee = (float) $payload['fee'];
        $payableAmount = $withdrawAmount + $fee; // Total amount to deduct from wallet

        $wallet = Wallet::create([
            'uuid' => 'wallet-easylink-test-uuid',
            'user_id' => $this->user->id,
            'currency_id' => self::CURRENCY_ID,
            'balance' => $initialBalance,
            'balance_sandbox' => 0,
            'hold_balance' => 0,
            'hold_balance_sandbox' => 0,
            'status' => true,
        ])->refresh();

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'trx_id' => $payload['reference_id'],
            'trx_type' => TrxType::WITHDRAW,
            'status' => TrxStatus::PENDING,
            'net_amount' => (int) $payableAmount,
            'payable_amount' => $payableAmount,
            'wallet_reference' => $wallet->uuid,
            'currency' => 'IDR',
            'processing_type' => MethodType::SYSTEM,
            'amount' => $payableAmount,
            'trx_reference' => (string) Str::uuid(),
        ])->refresh();

        $request = $this->createJsonRequest($payload, gatewayPath: 'easylink');

        $controller = app(IPNController::class);

        $response = $controller->handleIPN($request, 'easylink', 'disbursment');

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'status' => 'success',
            'message' => 'Webhook received',
        ], $response->getData(true));

        $this->assertSame(TrxStatus::COMPLETED, $transaction->fresh()->status);
        $this->assertSame($initialBalance - $payableAmount, $wallet->fresh()->getActualBalance());

        Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $event) use ($request): bool {
            $this->assertSame('easylink', $event->gateway);
            $this->assertSame('disbursment', $event->action);
            $this->assertSame($request->fullUrl(), $event->url);

            return true;
        });
    }

    /**
     * Test helper for easylink withdraw states that don't change transaction status.
     */
    private function testEasylinkWithdrawState(int $state, string $stateName, bool $expectStatusChange = false, ?TrxStatus $expectedStatus = null): void
    {
        Event::fake([WebhookReceived::class]);

        if (! isset($this->easylinkPayloads[$state])) {
            $this->markTestSkipped("Easylink state {$state} payload not available");
        }

        $payload = $this->easylinkPayloads[$state];
        $initialBalance = 5000.0;
        $withdrawAmount = (float) $payload['source_amount'];
        $fee = (float) $payload['fee'];
        $payableAmount = $withdrawAmount + $fee;

        $wallet = Wallet::create([
            'uuid' => "wallet-easylink-state-{$state}",
            'user_id' => $this->user->id,
            'currency_id' => self::CURRENCY_ID,
            'balance' => $initialBalance,
            'balance_sandbox' => 0,
            'hold_balance' => 0,
            'hold_balance_sandbox' => 0,
            'status' => true,
        ])->refresh();

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'trx_id' => $payload['reference_id'],
            'trx_type' => TrxType::WITHDRAW,
            'status' => TrxStatus::PENDING,
            'net_amount' => (int) $payableAmount,
            'payable_amount' => $payableAmount,
            'wallet_reference' => $wallet->uuid,
            'currency' => 'IDR',
            'processing_type' => MethodType::SYSTEM,
            'amount' => $payableAmount,
            'trx_reference' => (string) Str::uuid(),
        ])->refresh();

        $request = $this->createJsonRequest($payload, gatewayPath: 'easylink');
        $controller = app(IPNController::class);

        $response = $controller->handleIPN($request, 'easylink', 'disbursment');

        // Gateway returns false for states that don't trigger status changes, but still updates transaction data
        $expectsSuccess = $expectStatusChange;

        if ($expectsSuccess) {
            $this->assertSame(200, $response->status());
            $this->assertSame([
                'status' => 'success',
                'message' => 'Webhook received',
            ], $response->getData(true));
        } else {
            // States that don't change status return false, so response is 400 but still processed
            $this->assertSame(400, $response->status());
            $this->assertSame([
                'status' => 'failed',
                'message' => 'Webhook received',
            ], $response->getData(true));
        }

        // Verify transaction data is updated
        $transaction->refresh();
        $this->assertArrayHasKey('easylink_settlement', $transaction->trx_data);

        if ($expectStatusChange && $expectedStatus) {
            $this->assertSame($expectedStatus, $transaction->status);
        } else {
            $this->assertSame(TrxStatus::PENDING, $transaction->status);
        }

        Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $event): bool {
            $this->assertSame('easylink', $event->gateway);
            $this->assertSame('disbursment', $event->action);

            return true;
        });
    }

    public function test_handle_ipn_easylink_withdraw_state_01_create(): void
    {
        $this->testEasylinkWithdrawState(1, 'CREATE');
    }

    public function test_handle_ipn_easylink_withdraw_state_02_confirm(): void
    {
        $this->testEasylinkWithdrawState(2, 'CONFIRM');
    }

    public function test_handle_ipn_easylink_withdraw_state_03_hold(): void
    {
        $this->testEasylinkWithdrawState(3, 'HOLD');
    }

    public function test_handle_ipn_easylink_withdraw_state_04_review(): void
    {
        $this->testEasylinkWithdrawState(4, 'REVIEW');
    }

    public function test_handle_ipn_easylink_withdraw_state_05_payout(): void
    {
        $this->testEasylinkWithdrawState(5, 'PAYOUT');
    }

    public function test_handle_ipn_easylink_withdraw_state_06_sent(): void
    {
        $this->testEasylinkWithdrawState(6, 'SENT');
    }

    public function test_handle_ipn_easylink_withdraw_state_08_canceled(): void
    {
        $this->testEasylinkWithdrawState(8, 'CANCELED');
    }

    public function test_handle_ipn_easylink_withdraw_state_09_failed(): void
    {
        Event::fake([WebhookReceived::class]);

        if (! isset($this->easylinkPayloads[9])) {
            $this->markTestSkipped('Easylink state 9 payload not available');
        }

        $payload = $this->easylinkPayloads[9]; // State 9: FAILED
        $initialBalance = 5000.0;
        $withdrawAmount = (float) $payload['source_amount'];
        $fee = (float) $payload['fee'];
        $payableAmount = $withdrawAmount + $fee;

        $wallet = Wallet::create([
            'uuid' => 'wallet-easylink-failed',
            'user_id' => $this->user->id,
            'currency_id' => self::CURRENCY_ID,
            'balance' => $initialBalance,
            'balance_sandbox' => 0,
            'hold_balance' => 0,
            'hold_balance_sandbox' => 0,
            'status' => true,
        ])->refresh();

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'trx_id' => $payload['reference_id'],
            'trx_type' => TrxType::WITHDRAW,
            'status' => TrxStatus::PENDING,
            'net_amount' => (int) $payableAmount,
            'payable_amount' => $payableAmount,
            'wallet_reference' => $wallet->uuid,
            'currency' => 'IDR',
            'processing_type' => MethodType::SYSTEM,
            'amount' => $payableAmount,
            'trx_reference' => (string) Str::uuid(),
        ])->refresh();

        $request = $this->createJsonRequest($payload, gatewayPath: 'easylink');
        $controller = app(IPNController::class);

        $response = $controller->handleIPN($request, 'easylink', 'disbursment');

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'status' => 'success',
            'message' => 'Webhook received',
        ], $response->getData(true));

        $this->assertSame(TrxStatus::FAILED, $transaction->fresh()->status);
        $this->assertArrayHasKey('easylink_settlement', $transaction->fresh()->trx_data);
    }

    public function test_handle_ipn_easylink_withdraw_state_10_refund_success(): void
    {
        Event::fake([WebhookReceived::class]);

        if (! isset($this->easylinkPayloads[10])) {
            $this->markTestSkipped('Easylink state 10 payload not available');
        }

        $payload = $this->easylinkPayloads[10]; // State 10: REFUND_SUCCESS
        $initialBalance = 5000.0;
        $withdrawAmount = (float) $payload['source_amount'];
        $fee = (float) $payload['fee'];
        $payableAmount = $withdrawAmount + $fee;

        $wallet = Wallet::create([
            'uuid' => 'wallet-easylink-refund',
            'user_id' => $this->user->id,
            'currency_id' => self::CURRENCY_ID,
            'balance' => $initialBalance,
            'balance_sandbox' => 0,
            'hold_balance' => 0,
            'hold_balance_sandbox' => 0,
            'status' => true,
        ])->refresh();

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'trx_id' => $payload['reference_id'],
            'trx_type' => TrxType::WITHDRAW,
            'status' => TrxStatus::PENDING,
            'net_amount' => (int) $payableAmount,
            'payable_amount' => $payableAmount,
            'wallet_reference' => $wallet->uuid,
            'currency' => 'IDR',
            'processing_type' => MethodType::SYSTEM,
            'amount' => $payableAmount,
            'trx_reference' => (string) Str::uuid(),
        ])->refresh();

        $request = $this->createJsonRequest($payload, gatewayPath: 'easylink');
        $controller = app(IPNController::class);

        $response = $controller->handleIPN($request, 'easylink', 'disbursment');

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'status' => 'success',
            'message' => 'Webhook received',
        ], $response->getData(true));

        $this->assertSame(TrxStatus::FAILED, $transaction->fresh()->status);
        $this->assertArrayHasKey('easylink_settlement', $transaction->fresh()->trx_data);
    }

    public function test_handle_ipn_easylink_withdraw_state_26_processing_bank_partner(): void
    {
        $this->testEasylinkWithdrawState(26, 'PROCESSING_BANK_PARTNER');
    }

    public function test_handle_ipn_easylink_withdraw_state_27_remind_recipient(): void
    {
        $this->testEasylinkWithdrawState(27, 'REMIND_RECIPIENT');
    }

    /**
     * @throws \JsonException
     */
    private function createJsonRequest(array $payload, array $query = [], string $gatewayPath = 'netzme'): Request
    {
        $content = json_encode($payload, JSON_THROW_ON_ERROR);

        $request = Request::create(
            "/ipn/{$gatewayPath}",
            'POST',
            $payload, // This makes data accessible via $request->input()
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $content
        );

        // Merge JSON data into request so it's accessible via magic properties
        $request->merge($payload);

        foreach ($query as $key => $value) {
            $request->query->set($key, $value);
        }

        if ($query !== []) {
            $request->server->set('QUERY_STRING', http_build_query($query));
        }

        return $request;
    }
}
