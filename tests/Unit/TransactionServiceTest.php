<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Exceptions\NotifyErrorException;
use App\Models\Transaction;
use App\Services\Handlers\DepositHandler;
use App\Services\TransactionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('transactions');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('trx_id')->unique();
            $table->string('trx_type');
            $table->string('status')->default(TrxStatus::PENDING->value);
            $table->decimal('trx_fee', 18, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->string('description')->nullable();
            $table->string('wallet_reference')->nullable();
            $table->json('trx_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();

        Schema::dropIfExists('transactions');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_complete_transaction_throws_when_transaction_missing(): void
    {
        $service = app(TransactionService::class);

        $this->expectException(NotifyErrorException::class);
        $this->expectExceptionMessage('Transaction not found for ID');

        $service->completeTransaction('MISSING-ID');
    }

    public function test_complete_transaction_updates_status_and_invokes_handler_for_deposit(): void
    {
        $trxId = 'TRX-'.Str::uuid();

        $transaction = Transaction::create([
            'trx_id' => $trxId,
            'trx_type' => TrxType::DEPOSIT,
            'status' => TrxStatus::PENDING,
        ])->refresh();

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
        $trxId = 'TRX-'.Str::uuid();

        $transaction = Transaction::create([
            'trx_id' => $trxId,
            'trx_type' => TrxType::SEND_MONEY,
            'status' => TrxStatus::PENDING,
        ])->refresh();

        $service = app(TransactionService::class);
        $service->completeTransaction($trxId);

        $updated = $transaction->fresh();

        $this->assertEquals(TrxStatus::COMPLETED, $updated->status);
        $this->assertNull($updated->remarks);
        $this->assertNull($updated->description);
    }
}
