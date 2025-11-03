<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\NotifyErrorException;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_wallet_by_uuid_returns_existing_wallet(): void
    {
        $uuid = Str::uuid()->toString();

        $original = Wallet::create([
            'uuid' => $uuid,
            'user_id' => null,
            'currency_id' => 360,
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

        $this->expectException(NotifyErrorException::class);
        $this->expectExceptionMessage('Wallet with ID');

        $service->getWalletByUuId(Str::uuid()->toString());
    }

    public function test_subtract_money_throws_when_insufficient_available_balance(): void
    {
        $wallet = Wallet::create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => null,
            'currency_id' => 360,
            'balance' => 1000,
            'hold_balance' => 500,
            'status' => true,
        ])->refresh();

        $service = app(WalletService::class);

        $this->expectException(NotifyErrorException::class);
        $this->expectExceptionMessage('Insufficient available balance');

        $service->subtractMoney($wallet, 600);
    }

    public function test_add_money_increments_wallet_balance(): void
    {
        $wallet = Wallet::create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => null,
            'currency_id' => 360,
            'balance' => 1000,
            'status' => true,
        ])->refresh();

        $service = app(WalletService::class);

        $updated = $service->addMoney($wallet, 250);

        $this->assertEquals(1250.0, $updated->getActualBalance());
    }
}
