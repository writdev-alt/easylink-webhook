<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Enums\MethodType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Handlers\DepositHandler;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure migrations are applied for this unit test
        Artisan::call('migrate:fresh');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_complete_transaction_throws_when_transaction_missing(): void
    {
        $service = app(TransactionService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transaction not found for ID');

        $service->completeTransaction('MISSING-ID');
    }

    public function test_complete_transaction_updates_status_and_invokes_handler_for_deposit(): void
    {
        $user = User::factory()->create();
        $trxId = 'TRX-'.Str::uuid();

        $transaction = Transaction::create($this->transactionAttributes(
            userId: $user->id,
            trxId: $trxId,
            trxType: TrxType::DEPOSIT
        ))->refresh();

        $mock = Mockery::mock(DepositHandler::class);
        $mock->shouldReceive('handleSuccess')
            ->once()
            ->with(Mockery::on(fn (Transaction $trx) => $trx->trx_id === $trxId));

        app()->instance(DepositHandler::class, $mock);

        $service = app(TransactionService::class);
        $service->completeTransaction($trxId, 'Done', 'Completed via test');

        $updated = $transaction->fresh();

        $this->assertEquals(TrxStatus::COMPLETED, $updated->status);
        $this->assertSame('Done', $updated->remarks);
        $this->assertSame('Completed via test', $updated->description);
    }

    public function test_complete_transaction_updates_status_even_without_handler(): void
    {
        $user = User::factory()->create();
        $trxId = 'TRX-'.Str::uuid();

        $transaction = Transaction::create($this->transactionAttributes(
            userId: $user->id,
            trxId: $trxId,
            trxType: TrxType::SEND_MONEY
        ))->refresh();

        $service = app(TransactionService::class);
        $service->completeTransaction($trxId);

        $updated = $transaction->fresh();

        $this->assertEquals(TrxStatus::COMPLETED, $updated->status);
        $this->assertNull($updated->remarks);
        $this->assertNull($updated->description);
    }

    /**
     * Build transaction attributes consistent with schema requirements.
     */
    protected function transactionAttributes(int $userId, string $trxId, TrxType $trxType, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $userId,
            'trx_id' => $trxId,
            'trx_type' => $trxType,
            'trx_reference' => 'REF-'.Str::uuid(),
            'processing_type' => MethodType::AUTOMATIC,
            'amount' => 100,
            'net_amount' => 100,
            'currency' => 'USD',
            'status' => TrxStatus::PENDING,
        ], $overrides);
    }
}
