<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants\CurrencyType;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletServiceTest extends TestCase
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

    public function test_get_wallet_by_uuid_returns_existing_wallet(): void
    {
        $uuid = Str::uuid()->toString();

        $original = Wallet::create([
            'uuid' => $uuid,
            'user_id' => $this->user->id,
            'currency_id' => self::CURRENCY_ID,
            'balance' => 150000,
            'balance_sandbox' => 0,
            'hold_balance' => 0,
            'hold_balance_sandbox' => 0,
            'status' => true,
        ])->refresh();

        $service = app(WalletService::class);

        $wallet = $service->getWalletByUuId($uuid);

        $this->assertSame($original->uuid, $wallet->uuid);
        $this->assertSame(150000.0, $wallet->getActualBalance());
    }

    public function test_get_wallet_by_uuid_throws_when_wallet_missing(): void
    {
        $service = app(WalletService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Wallet with ID');

        $service->getWalletByUuId(Str::uuid()->toString());
    }

    public function test_subtract_money_throws_when_insufficient_available_balance(): void
    {
        $wallet = Wallet::create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'currency_id' => self::CURRENCY_ID,
            'balance' => 1000,
            'hold_balance' => 500,
            'status' => true,
        ])->refresh();

        $service = app(WalletService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient balance in wallet.');

        $service->subtractMoney($wallet, 1500);
    }

    public function test_add_money_increments_wallet_balance(): void
    {
        $wallet = Wallet::create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'currency_id' => self::CURRENCY_ID,
            'balance' => 1000,
            'status' => true,
        ])->refresh();

        $service = app(WalletService::class);

        $updated = $service->addMoney($wallet, 250);

        $this->assertEquals(1250.0, $updated->getActualBalance());
    }
}
