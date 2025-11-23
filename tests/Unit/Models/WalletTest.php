<?php

namespace Tests\Unit\Models;

use App\Constants\CurrencyType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENCY_ID = 360;

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
    }

    public function test_wallet_can_be_created_with_required_attributes()
    {
        $user = User::factory()->create();

        $walletData = [
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'wallet-uuid-123',
            'balance' => 1000.50,
            'hold_balance' => 100.25,
            'balance_sandbox' => 500.00,
            'hold_balance_sandbox' => 50.00,
            'status' => true,
        ];

        $wallet = Wallet::create($walletData);

        $this->assertInstanceOf(Wallet::class, $wallet);
        $this->assertEquals($user->id, $wallet->user_id);
        $this->assertEquals(360, $wallet->currency_id);
        $this->assertEquals('wallet-uuid-123', $wallet->uuid);
        $this->assertEquals(1000.50, $wallet->balance);
        $this->assertEquals(100.25, $wallet->hold_balance);
        $this->assertEquals(500.00, $wallet->balance_sandbox);
        $this->assertEquals(50.00, $wallet->hold_balance_sandbox);
        $this->assertTrue($wallet->status);
    }

    public function test_wallet_fillable_attributes()
    {
        $wallet = new Wallet;
        $expectedFillable = [
            'user_id',
            'currency_id',
            'uuid',
            'balance',
            'hold_balance',
            'balance_sandbox',
            'hold_balance_sandbox',
            'status',
        ];

        $this->assertEquals($expectedFillable, $wallet->getFillable());
    }

    public function test_wallet_casts()
    {
        $wallet = new Wallet;
        $expectedCasts = [
            'id' => 'int',
            'balance' => 'float',
            'hold_balance' => 'float',
            'balance_sandbox' => 'float',
            'hold_balance_sandbox' => 'float',
            'status' => 'boolean',
        ];

        $this->assertEquals($expectedCasts, $wallet->getCasts());
    }

    public function test_wallet_belongs_to_user()
    {
        $wallet = new Wallet;

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $wallet->user()
        );
    }

    public function test_wallet_has_many_transactions()
    {
        $wallet = new Wallet;

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $wallet->transactions()
        );
    }

    public function test_wallet_currency_role_accessors()
    {
        $user = User::factory()->create();

        // Test with different currency IDs to check role accessors
        $paymentWallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360, // IDR - typically payment currency
            'uuid' => 'payment-wallet-uuid',
            'balance' => 1000.00,
            'status' => true,
        ]);

        // These accessors depend on CurrencyRole constants
        // We'll test that they return boolean values
        $this->assertIsBool($paymentWallet->is_payment);
        $this->assertIsBool($paymentWallet->is_receiver);
        $this->assertIsBool($paymentWallet->is_request_money);
        $this->assertIsBool($paymentWallet->is_sender);
        $this->assertIsBool($paymentWallet->is_withdraw);
    }

    public function test_wallet_uuid_must_be_unique()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Wallet::create([
            'user_id' => $user1->id,
            'currency_id' => 360,
            'uuid' => 'unique-wallet-uuid',
            'balance' => 1000.00,
            'status' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Wallet::create([
            'user_id' => $user2->id,
            'currency_id' => 360,
            'uuid' => 'unique-wallet-uuid',
            'balance' => 500.00,
            'status' => true,
        ]);
    }

    public function test_wallet_balance_defaults_to_zero()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'test-wallet-uuid',
            'status' => true,
        ]);

        $this->assertEquals(0.0, $wallet->balance);
        $this->assertEquals(0.0, $wallet->hold_balance);
        $this->assertEquals(0.0, $wallet->balance_sandbox);
        $this->assertEquals(0.0, $wallet->hold_balance_sandbox);
    }

    public function test_wallet_status_defaults_to_true()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'test-wallet-uuid',
        ]);

        $this->assertTrue($wallet->status);
    }

    public function test_wallet_can_be_updated()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'test-wallet-uuid',
            'balance' => 1000.00,
            'status' => true,
        ]);

        $wallet->update([
            'balance' => 1500.00,
            'hold_balance' => 200.00,
            'status' => false,
        ]);

        $this->assertEquals(1500.00, $wallet->balance);
        $this->assertEquals(200.00, $wallet->hold_balance);
        $this->assertFalse($wallet->status);
    }

    public function test_wallet_timestamps_are_set()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'test-wallet-uuid',
            'status' => true,
        ]);

        $this->assertNotNull($wallet->created_at);
        $this->assertNotNull($wallet->updated_at);
    }

    public function test_wallet_can_increment_balance()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'test-wallet-uuid',
            'balance' => 1000.00,
            'status' => true,
        ]);

        $wallet->increment('balance', 250.50);

        $this->assertEquals(1250.50, $wallet->fresh()->balance);
    }

    public function test_wallet_can_decrement_balance()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'test-wallet-uuid',
            'balance' => 1000.00,
            'status' => true,
        ]);

        $wallet->decrement('balance', 250.50);

        $this->assertEquals(749.50, $wallet->fresh()->balance);
    }

    public function test_wallet_can_increment_balance_sandbox()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'test-wallet-uuid',
            'balance_sandbox' => 500.00,
            'status' => true,
        ]);

        $wallet->increment('balance_sandbox', 150.25);

        $this->assertEquals(650.25, $wallet->fresh()->balance_sandbox);
    }

    public function test_wallet_can_decrement_balance_sandbox()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'test-wallet-uuid',
            'balance_sandbox' => 500.00,
            'status' => true,
        ]);

        $wallet->decrement('balance_sandbox', 150.25);

        $this->assertEquals(349.75, $wallet->fresh()->balance_sandbox);
    }

    public function test_wallet_can_increment_both_balance_and_balance_sandbox_independently()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'test-wallet-uuid',
            'balance' => 1000.00,
            'balance_sandbox' => 500.00,
            'status' => true,
        ]);

        $wallet->increment('balance', 200.00);
        $wallet->increment('balance_sandbox', 100.00);

        $freshWallet = $wallet->fresh();
        $this->assertEquals(1200.00, $freshWallet->balance);
        $this->assertEquals(600.00, $freshWallet->balance_sandbox);
    }

    public function test_wallet_can_decrement_both_balance_and_balance_sandbox_independently()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => 360,
            'uuid' => 'test-wallet-uuid',
            'balance' => 1000.00,
            'balance_sandbox' => 500.00,
            'status' => true,
        ]);

        $wallet->decrement('balance', 200.00);
        $wallet->decrement('balance_sandbox', 100.00);

        $freshWallet = $wallet->fresh();
        $this->assertEquals(800.00, $freshWallet->balance);
        $this->assertEquals(400.00, $freshWallet->balance_sandbox);
    }
}
