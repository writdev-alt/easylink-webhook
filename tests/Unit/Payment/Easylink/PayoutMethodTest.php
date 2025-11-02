<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Easylink;

use App\Payment\Easylink\Enums\PayoutMethod;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ValueError;

#[CoversClass(PayoutMethod::class)]
class PayoutMethodTest extends TestCase
{
    #[DataProvider('providerNameCases')]
    public function test_from_provider_name_returns_expected_value(string $provider, int $expected): void
    {
        $this->assertSame($expected, PayoutMethod::fromProviderName($provider));
    }

    public static function providerNameCases(): array
    {
        return [
            'bank transfer' => ['Bank Transfer', PayoutMethod::BANK_TRANSFER->value],
            'easylink bank transfer' => ['Easylink Bank Transfer', PayoutMethod::BANK_TRANSFER->value],
            'virtual account transfer' => ['Virtual Account Transfer', PayoutMethod::VIRTUAL_ACCOUNT_TRANSFER->value],
            'easylink virtual account transfer' => ['Easylink Virtual Account Transfer', PayoutMethod::VIRTUAL_ACCOUNT_TRANSFER->value],
            'wallet transfer' => ['Wallet Transfer', PayoutMethod::WALLET_TRANSFER->value],
            'easylink wallet transfer' => ['Easylink Wallet Transfer', PayoutMethod::WALLET_TRANSFER->value],
        ];
    }

    public function test_from_provider_name_returns_zero_for_unknown_provider(): void
    {
        $this->assertSame(0, PayoutMethod::fromProviderName('Unsupported Provider'));
    }

    #[DataProvider('idCases')]
    public function test_from_id_returns_enum_instance(int $id, PayoutMethod $expected): void
    {
        $this->assertSame($expected, PayoutMethod::fromId($id));
    }

    public static function idCases(): array
    {
        return [
            'bank transfer' => [PayoutMethod::BANK_TRANSFER->value, PayoutMethod::BANK_TRANSFER],
            'virtual account transfer' => [PayoutMethod::VIRTUAL_ACCOUNT_TRANSFER->value, PayoutMethod::VIRTUAL_ACCOUNT_TRANSFER],
            'wallet transfer' => [PayoutMethod::WALLET_TRANSFER->value, PayoutMethod::WALLET_TRANSFER],
        ];
    }

    public function test_from_id_throws_for_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PayoutMethod::fromId(999);
    }

    #[DataProvider('statusCases')]
    public function test_from_status_code_returns_matching_enum_case(int $value, PayoutMethod $expected): void
    {
        $this->assertSame($expected, PayoutMethod::fromStatusCode($value));
    }

    public static function statusCases(): array
    {
        return [
            'bank transfer' => [PayoutMethod::BANK_TRANSFER->value, PayoutMethod::BANK_TRANSFER],
            'virtual account transfer' => [PayoutMethod::VIRTUAL_ACCOUNT_TRANSFER->value, PayoutMethod::VIRTUAL_ACCOUNT_TRANSFER],
            'wallet transfer' => [PayoutMethod::WALLET_TRANSFER->value, PayoutMethod::WALLET_TRANSFER],
        ];
    }

    public function test_from_status_code_throws_for_invalid_value(): void
    {
        $this->expectException(ValueError::class);

        PayoutMethod::fromStatusCode(0);
    }
}


