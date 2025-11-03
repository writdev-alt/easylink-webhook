<?php

namespace Tests\Unit\Models;

use App\Enums\AmountFlow;
use App\Enums\MethodType;
use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_can_be_created_with_required_attributes()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        
        $transactionData = [
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'trx_id' => 'TXN123456789',
            'trx_type' => TrxType::DEPOSIT,
            'description' => 'Test deposit transaction',
            'processing_type' => MethodType::AUTOMATIC,
            'amount' => 1000.00,
            'amount_flow' => AmountFlow::PLUS,
            'ma_fee' => 10.00,
            'mdr_fee' => 5.00,
            'admin_fee' => 2.00,
            'agent_fee' => 3.00,
            'cashback_fee' => 1.00,
            'trx_fee' => 20.00,
            'currency' => 'IDR',
            'net_amount' => 980,
            'payable_amount' => 980.00,
            'payable_currency' => 'IDR',
            'wallet_reference' => 'wallet-uuid-123',
            'trx_reference' => 'ref-123',
            'status' => TrxStatus::PENDING,
        ];

        $transaction = Transaction::create($transactionData);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($user->id, $transaction->user_id);
        $this->assertEquals($customer->id, $transaction->customer_id);
        $this->assertEquals('TXN123456789', $transaction->trx_id);
        $this->assertEquals(TrxType::DEPOSIT, $transaction->trx_type);
        $this->assertEquals('Test deposit transaction', $transaction->description);
        $this->assertEquals(MethodType::AUTOMATIC, $transaction->processing_type);
        $this->assertEquals(1000.00, $transaction->amount);
        $this->assertEquals(AmountFlow::PLUS, $transaction->amount_flow);
        $this->assertEquals(10.00, $transaction->ma_fee);
        $this->assertEquals(5.00, $transaction->mdr_fee);
        $this->assertEquals(2.00, $transaction->admin_fee);
        $this->assertEquals(3.00, $transaction->agent_fee);
        $this->assertEquals(1.00, $transaction->cashback_fee);
        $this->assertEquals(20.00, $transaction->trx_fee);
        $this->assertEquals('IDR', $transaction->currency);
        $this->assertEquals(980, $transaction->net_amount);
        $this->assertEquals(980.00, $transaction->payable_amount);
        $this->assertEquals('IDR', $transaction->payable_currency);
        $this->assertEquals('wallet-uuid-123', $transaction->wallet_reference);
        $this->assertEquals('ref-123', $transaction->trx_reference);
        $this->assertEquals(TrxStatus::PENDING, $transaction->status);
    }

    public function test_transaction_fillable_attributes()
    {
        $transaction = new Transaction();
        $expectedFillable = [
            'merchant_aggregator_store_nmid',
            'merchant_id',
            'user_id',
            'customer_id',
            'trx_id',
            'trx_type',
            'description',
            'provider',
            'method_id',
            'method_type',
            'processing_type',
            'amount',
            'amount_flow',
            'ma_fee',
            'mdr_fee',
            'admin_fee',
            'agent_fee',
            'cashback_fee',
            'trx_fee',
            'currency',
            'net_amount',
            'payable_amount',
            'payable_currency',
            'wallet_reference',
            'trx_reference',
            'trx_data',
            'remarks',
            'status',
            'released_at',
        ];

        $this->assertEquals($expectedFillable, $transaction->getFillable());
    }

    public function test_transaction_casts()
    {
        $transaction = new Transaction();
        $expectedCasts = [
            'id' => 'int',
            'trx_type' => TrxType::class,
            'processing_type' => MethodType::class,
            'amount_flow' => AmountFlow::class,
            'status' => TrxStatus::class,
            'trx_data' => 'array',
            'released_at' => 'datetime',
        ];

        $this->assertEquals($expectedCasts, $transaction->getCasts());
    }

    public function test_transaction_belongs_to_user()
    {
        $transaction = new Transaction();
        
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $transaction->user()
        );
    }

    public function test_transaction_belongs_to_customer()
    {
        $transaction = new Transaction();
        
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $transaction->customer()
        );
    }

    public function test_transaction_belongs_to_merchant()
    {
        $transaction = new Transaction();
        
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $transaction->merchant()
        );
    }

    public function test_transaction_morph_to_method()
    {
        $transaction = new Transaction();
        
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\MorphTo::class,
            $transaction->method()
        );
    }

    public function test_transaction_trx_id_must_be_unique()
    {
        $user = User::factory()->create();
        
        Transaction::create([
            'user_id' => $user->id,
            'trx_id' => 'UNIQUE_TXN_123',
            'trx_type' => TrxType::DEPOSIT,
            'processing_type' => MethodType::AUTOMATIC,
            'amount' => 1000.00,
            'currency' => 'IDR',
            'net_amount' => 1000,
            'status' => TrxStatus::PENDING,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Transaction::create([
            'user_id' => $user->id,
            'trx_id' => 'UNIQUE_TXN_123',
            'trx_type' => TrxType::WITHDRAW,
            'processing_type' => MethodType::MANUAL,
            'amount' => 500.00,
            'currency' => 'IDR',
            'net_amount' => 500,
            'status' => TrxStatus::PENDING,
        ]);
    }

    public function test_transaction_status_defaults_to_pending()
    {
        $user = User::factory()->create();
        
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'trx_id' => 'TXN_DEFAULT_STATUS',
            'trx_type' => TrxType::DEPOSIT,
            'processing_type' => MethodType::AUTOMATIC,
            'amount' => 1000.00,
            'currency' => 'IDR',
            'net_amount' => 1000,
        ]);

        $this->assertEquals(TrxStatus::PENDING, $transaction->status);
    }

    public function test_transaction_can_store_json_data()
    {
        $user = User::factory()->create();
        
        $trxData = [
            'gateway' => 'easylink',
            'reference_id' => 'EL123456',
            'callback_url' => 'https://example.com/callback',
            'metadata' => [
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0',
            ],
        ];

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'trx_id' => 'TXN_JSON_DATA',
            'trx_type' => TrxType::DEPOSIT,
            'processing_type' => MethodType::AUTOMATIC,
            'amount' => 1000.00,
            'currency' => 'IDR',
            'net_amount' => 1000,
            'trx_data' => $trxData,
            'status' => TrxStatus::PENDING,
        ]);

        $this->assertEquals($trxData, $transaction->trx_data);
        $this->assertEquals('easylink', $transaction->trx_data['gateway']);
        $this->assertEquals('192.168.1.1', $transaction->trx_data['metadata']['ip_address']);
    }

    public function test_transaction_can_be_updated()
    {
        $user = User::factory()->create();
        
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'trx_id' => 'TXN_UPDATE_TEST',
            'trx_type' => TrxType::DEPOSIT,
            'processing_type' => MethodType::AUTOMATIC,
            'amount' => 1000.00,
            'currency' => 'IDR',
            'net_amount' => 1000,
            'status' => TrxStatus::PENDING,
        ]);

        $transaction->update([
            'status' => TrxStatus::COMPLETED,
            'remarks' => 'Transaction completed successfully',
            'released_at' => now(),
        ]);

        $this->assertEquals(TrxStatus::COMPLETED, $transaction->status);
        $this->assertEquals('Transaction completed successfully', $transaction->remarks);
        $this->assertNotNull($transaction->released_at);
    }

    public function test_transaction_soft_deletes()
    {
        $user = User::factory()->create();
        
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'trx_id' => 'TXN_SOFT_DELETE',
            'trx_type' => TrxType::DEPOSIT,
            'processing_type' => MethodType::AUTOMATIC,
            'amount' => 1000.00,
            'currency' => 'IDR',
            'net_amount' => 1000,
            'status' => TrxStatus::PENDING,
        ]);

        $transactionId = $transaction->id;
        $transaction->delete();

        // Should not find the transaction in normal queries
        $this->assertNull(Transaction::find($transactionId));
        
        // Should find the transaction when including trashed
        $this->assertNotNull(Transaction::withTrashed()->find($transactionId));
    }

    public function test_transaction_timestamps_are_set()
    {
        $user = User::factory()->create();
        
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'trx_id' => 'TXN_TIMESTAMPS',
            'trx_type' => TrxType::DEPOSIT,
            'processing_type' => MethodType::AUTOMATIC,
            'amount' => 1000.00,
            'currency' => 'IDR',
            'net_amount' => 1000,
            'status' => TrxStatus::PENDING,
        ]);

        $this->assertNotNull($transaction->created_at);
        $this->assertNotNull($transaction->updated_at);
    }

    public function test_transaction_enum_values()
    {
        $user = User::factory()->create();
        
        // Test different transaction types
        $depositTransaction = Transaction::create([
            'user_id' => $user->id,
            'trx_id' => 'TXN_DEPOSIT',
            'trx_type' => TrxType::DEPOSIT,
            'processing_type' => MethodType::AUTOMATIC,
            'amount' => 1000.00,
            'currency' => 'IDR',
            'net_amount' => 1000,
            'status' => TrxStatus::PENDING,
        ]);

        $withdrawTransaction = Transaction::create([
            'user_id' => $user->id,
            'trx_id' => 'TXN_WITHDRAW',
            'trx_type' => TrxType::WITHDRAW,
            'processing_type' => MethodType::MANUAL,
            'amount' => 500.00,
            'amount_flow' => AmountFlow::MINUS,
            'currency' => 'IDR',
            'net_amount' => 500,
            'status' => TrxStatus::AWAITING_ADMIN_APPROVAL,
        ]);

        $this->assertEquals(TrxType::DEPOSIT, $depositTransaction->trx_type);
        $this->assertEquals(TrxType::WITHDRAW, $withdrawTransaction->trx_type);
        $this->assertEquals(MethodType::AUTOMATIC, $depositTransaction->processing_type);
        $this->assertEquals(MethodType::MANUAL, $withdrawTransaction->processing_type);
        $this->assertEquals(AmountFlow::MINUS, $withdrawTransaction->amount_flow);
        $this->assertEquals(TrxStatus::PENDING, $depositTransaction->status);
        $this->assertEquals(TrxStatus::AWAITING_ADMIN_APPROVAL, $withdrawTransaction->status);
    }
}