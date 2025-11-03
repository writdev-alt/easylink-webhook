<?php

namespace Tests\Unit\Payment\Easylink;

use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Models\Transaction;
use App\Payment\Easylink\EasylinkPaymentGateway;
use App\Payment\Easylink\Enums\TransferState;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class EasylinkPaymentGatewayTest extends TestCase
{
    private EasylinkPaymentGateway $gateway;

    private TransactionService $mockTransactionService;

    protected function setUp(): void
    {
        parent::setUp();
        // Apply migrations to ensure transactions schema comes from migration
        Artisan::call('migrate:fresh');
        $this->gateway = new EasylinkPaymentGateway;
        $this->mockTransactionService = Mockery::mock(TransactionService::class);
        $this->app->instance(TransactionService::class, $this->mockTransactionService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_disbursement_returns_false_when_transaction_not_found()
    {
        $request = new Request([
            'reference_id' => 'non_existent_transaction',
            'state' => TransferState::COMPLETE->value,
        ]);

        $result = $this->gateway->handleDisbursment($request);

        $this->assertFalse($result);
    }

    public function test_handle_disbursement_completes_transaction_when_state_is_complete()
    {
        $transaction = Transaction::create([
            'trx_id' => 'TEST_TRX_123',
            'trx_type' => TrxType::WITHDRAW,
            'status' => TrxStatus::PENDING,
            'trx_data' => ['existing' => 'data'],
        ]);

        $request = new Request([
            'reference_id' => 'TEST_TRX_123',
            'state' => TransferState::COMPLETE->value,
            'amount' => 1000,
            'currency' => 'IDR',
        ]);

        $this->mockTransactionService
            ->shouldReceive('completeTransaction')
            ->once()
            ->with('TEST_TRX_123');

        $result = $this->gateway->handleDisbursment($request);

        $this->assertTrue($result);

        $transaction->refresh();
        $this->assertArrayHasKey('easylink_settlement', $transaction->trx_data);
        $this->assertEquals($request->toArray(), $transaction->trx_data['easylink_settlement']);
        $this->assertEquals('data', $transaction->trx_data['existing']);
    }

    public function test_handle_disbursement_fails_transaction_when_state_is_failed()
    {
        $transaction = Transaction::create([
            'trx_id' => 'TEST_TRX_456',
            'trx_type' => TrxType::WITHDRAW,
            'status' => TrxStatus::PENDING,
        ]);

        $request = new Request([
            'reference_id' => 'TEST_TRX_456',
            'state' => TransferState::FAILED->value,
        ]);

        $this->mockTransactionService
            ->shouldReceive('failTransaction')
            ->once()
            ->with('TEST_TRX_456', 'Easylink Disbursement Failed', 'Withdrawal failed');

        $result = $this->gateway->handleDisbursment($request);

        $this->assertTrue($result);
    }

    public function test_handle_disbursement_fails_transaction_when_state_is_refund_success()
    {
        $transaction = Transaction::create([
            'trx_id' => 'TEST_TRX_789',
            'trx_type' => TrxType::WITHDRAW,
            'status' => TrxStatus::PENDING,
        ]);

        $request = new Request([
            'reference_id' => 'TEST_TRX_789',
            'state' => TransferState::REFUND_SUCCESS->value,
        ]);

        $this->mockTransactionService
            ->shouldReceive('failTransaction')
            ->once()
            ->with('TEST_TRX_789', 'Easylink Disbursement Refunded', 'Withdrawal refunded');

        $result = $this->gateway->handleDisbursment($request);

        $this->assertTrue($result);
    }

    public function test_handle_disbursement_returns_false_for_unknown_state()
    {
        $transaction = Transaction::create([
            'trx_id' => 'TEST_TRX_UNKNOWN',
            'trx_type' => TrxType::WITHDRAW,
            'status' => TrxStatus::PENDING,
        ]);

        $request = new Request([
            'reference_id' => 'TEST_TRX_UNKNOWN',
            'state' => 999, // Unknown state
        ]);

        $result = $this->gateway->handleDisbursment($request);

        $this->assertFalse($result);
    }

    public function test_handle_disbursement_only_processes_withdraw_transactions()
    {
        $depositTransaction = Transaction::create([
            'trx_id' => 'DEPOSIT_TRX',
            'trx_type' => TrxType::DEPOSIT,
            'status' => TrxStatus::PENDING,
        ]);

        $request = new Request([
            'reference_id' => 'DEPOSIT_TRX',
            'state' => TransferState::COMPLETE->value,
        ]);

        $result = $this->gateway->handleDisbursment($request);

        $this->assertFalse($result);
    }

    public function test_handle_topup_returns_false_when_transaction_not_found()
    {
        $request = new Request([
            'reference_id' => 'non_existent_topup',
            'state' => TransferState::COMPLETE->value,
        ]);

        $result = $this->gateway->handleTopup($request);

        $this->assertFalse($result);
    }

    public function test_handle_topup_completes_transaction_when_state_is_complete()
    {
        $transaction = Transaction::create([
            'trx_id' => 'TOPUP_TRX_123',
            'status' => TrxStatus::PENDING,
            'trx_data' => ['existing' => 'topup_data'],
        ]);

        $request = new Request([
            'reference_id' => 'TOPUP_TRX_123',
            'state' => TransferState::COMPLETE->value,
            'amount' => 5000,
        ]);

        $this->mockTransactionService
            ->shouldReceive('completeTransaction')
            ->once()
            ->with('TOPUP_TRX_123');

        $result = $this->gateway->handleTopup($request);

        $this->assertTrue($result);

        $transaction->refresh();
        $this->assertArrayHasKey('easylink_topup', $transaction->trx_data);
        $this->assertEquals($request->all(), $transaction->trx_data['easylink_topup']);
    }

    public function test_handle_topup_does_not_complete_already_completed_transaction()
    {
        $transaction = Transaction::create([
            'trx_id' => 'COMPLETED_TOPUP',
            'status' => TrxStatus::COMPLETED,
        ]);

        $request = new Request([
            'reference_id' => 'COMPLETED_TOPUP',
            'state' => TransferState::COMPLETE->value,
        ]);

        $this->mockTransactionService
            ->shouldNotReceive('completeTransaction');

        $result = $this->gateway->handleTopup($request);

        $this->assertTrue($result);
    }

    public function test_handle_topup_fails_transaction_when_state_is_failed()
    {
        $transaction = Transaction::create([
            'trx_id' => 'FAILED_TOPUP',
            'status' => TrxStatus::PENDING,
        ]);

        $request = new Request([
            'reference_id' => 'FAILED_TOPUP',
            'state' => TransferState::FAILED->value,
        ]);

        $this->mockTransactionService
            ->shouldReceive('failTransaction')
            ->once()
            ->with('FAILED_TOPUP', 'Easylink Topup Failed', 'Topup failed');

        $result = $this->gateway->handleTopup($request);

        $this->assertTrue($result);
    }

    public function test_handle_topup_fails_transaction_when_state_is_refund_success()
    {
        $transaction = Transaction::create([
            'trx_id' => 'REFUNDED_TOPUP',
            'status' => TrxStatus::PENDING,
        ]);

        $request = new Request([
            'reference_id' => 'REFUNDED_TOPUP',
            'state' => TransferState::REFUND_SUCCESS->value,
        ]);

        $this->mockTransactionService
            ->shouldReceive('failTransaction')
            ->once()
            ->with('REFUNDED_TOPUP', 'Easylink Topup Refunded', 'Topup refunded');

        $result = $this->gateway->handleTopup($request);

        $this->assertTrue($result);
    }

    public function test_handle_topup_returns_false_for_unknown_state()
    {
        $transaction = Transaction::create([
            'trx_id' => 'UNKNOWN_STATE_TOPUP',
            'status' => TrxStatus::PENDING,
        ]);

        $request = new Request([
            'reference_id' => 'UNKNOWN_STATE_TOPUP',
            'state' => 888, // Unknown state
        ]);

        $result = $this->gateway->handleTopup($request);

        $this->assertFalse($result);
    }

    public function test_handle_disbursement_updates_transaction_data_correctly()
    {
        $transaction = Transaction::create([
            'trx_id' => 'DATA_UPDATE_TEST',
            'trx_type' => TrxType::WITHDRAW,
            'status' => TrxStatus::PENDING,
            'trx_data' => [
                'original_data' => 'preserved',
                'easylink_settlement' => 'should_be_overwritten',
            ],
        ]);

        $requestData = [
            'reference_id' => 'DATA_UPDATE_TEST',
            'state' => TransferState::COMPLETE->value,
            'amount' => 2500,
            'fee' => 50,
            'timestamp' => '2024-01-01 12:00:00',
        ];

        $request = new Request($requestData);

        $this->mockTransactionService
            ->shouldReceive('completeTransaction')
            ->once();

        $this->gateway->handleDisbursment($request);

        $transaction->refresh();
        $this->assertEquals('preserved', $transaction->trx_data['original_data']);
        $this->assertEquals($requestData, $transaction->trx_data['easylink_settlement']);
    }

    public function test_handle_topup_updates_transaction_data_correctly()
    {
        $transaction = Transaction::create([
            'trx_id' => 'TOPUP_DATA_TEST',
            'status' => TrxStatus::PENDING,
            'trx_data' => [
                'original_topup_data' => 'preserved',
            ],
        ]);

        $requestData = [
            'reference_id' => 'TOPUP_DATA_TEST',
            'state' => TransferState::COMPLETE->value,
            'topup_amount' => 10000,
            'processing_fee' => 100,
        ];

        $request = new Request($requestData);

        $this->mockTransactionService
            ->shouldReceive('completeTransaction')
            ->once();

        $this->gateway->handleTopup($request);

        $transaction->refresh();
        $this->assertEquals('preserved', $transaction->trx_data['original_topup_data']);
        $this->assertEquals($requestData, $transaction->trx_data['easylink_topup']);
    }
}
