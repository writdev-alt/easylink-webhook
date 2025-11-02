<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Events\WebhookReceived;
use App\Http\Controllers\IPNController;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Payment\PaymentGatewayFactory;
use App\Services\TransactionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(IPNController::class)]
class IPNControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private array $payload;

    private array $pendingPayload;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('transactions');
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

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('trx_id')->unique();
            $table->string('trx_type');
            $table->string('status')->default(TrxStatus::PENDING->value);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->decimal('trx_fee', 18, 2)->default(0);
            $table->string('wallet_reference')->nullable();
            $table->string('remarks')->nullable();
            $table->string('description')->nullable();
            $table->json('trx_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');

        parent::tearDown();
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
            'status' => 'accepted',
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
            'status' => 'queued',
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
            'user_id' => null,
            'currency_id' => 360,
            'balance' => $initialBalance,
            'balance_sandbox' => 0,
            'hold_balance' => 0,
            'hold_balance_sandbox' => 0,
            'status' => true,
        ])->refresh();

        $transaction = Transaction::create([
            'trx_id' => $this->payload['originalPartnerReferenceNo'],
            'trx_type' => TrxType::DEPOSIT,
            'status' => TrxStatus::PENDING,
            'net_amount' => $netAmount,
            'wallet_reference' => $wallet->uuid,
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
            'user_id' => null,
            'currency_id' => 360,
            'balance' => $initialBalance,
            'balance_sandbox' => 0,
            'hold_balance' => 0,
            'hold_balance_sandbox' => 0,
            'status' => true,
        ])->refresh();

        $transaction = Transaction::create([
            'trx_id' => $this->pendingPayload['originalPartnerReferenceNo'],
            'trx_type' => TrxType::DEPOSIT,
            'status' => TrxStatus::PENDING,
            'net_amount' => (int) $this->pendingPayload['netAmount']['value'],
            'wallet_reference' => $wallet->uuid,
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
            'status' => 'pending',
            'message' => 'Webhook received',
        ], $response->getData(true));

        $this->assertSame($initialBalance, $wallet->fresh()->getActualBalance());
        $this->assertSame(TrxStatus::PENDING, $transaction->fresh()->status);
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
            $payload,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $content
        );

        foreach ($query as $key => $value) {
            $request->query->set($key, $value);
        }

        if ($query !== []) {
            $request->server->set('QUERY_STRING', http_build_query($query));
        }

        return $request;
    }
}

