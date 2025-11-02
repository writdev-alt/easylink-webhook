<?php

declare(strict_types=1);

namespace App\Payment\Easylink\Enums;

use InvalidArgumentException;
use ValueError;

/**
 * Represents the available payout methods for transactions.
 *
 * This enum provides a type-safe and consistent way to handle payment methods,
 * mapping them to their corresponding integer values and human-readable labels.
 */
enum PayoutMethod: int
{
    case BANK_TRANSFER = 1;
    case VIRTUAL_ACCOUNT_TRANSFER = 2;
    case WALLET_TRANSFER = 3;

    /**
     * Returns a human-readable label for the payout method.
     */
    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Bank Transfer',
            self::VIRTUAL_ACCOUNT_TRANSFER => 'Virtual Account Transfer',
            self::WALLET_TRANSFER => 'Wallet Transfer',
        };
    }

    /**
     * Converts a provider name string to its corresponding PayoutMethod enum case.
     *
     * @param  string  $provider  The provider name string.
     * @return self
     *
     * @throws InvalidArgumentException if the provider name is not supported.
     */
    public static function fromProviderName(string $provider): int
    {
        return match ($provider) {
            'Bank Transfer' => self::BANK_TRANSFER->value,
            'Easylink Bank Transfer' => self::BANK_TRANSFER->value,
            'Virtual Account Transfer' => self::VIRTUAL_ACCOUNT_TRANSFER->value,
            'Easylink Virtual Account Transfer' => self::VIRTUAL_ACCOUNT_TRANSFER->value,
            'Wallet Transfer' => self::WALLET_TRANSFER->value,
            'Easylink Wallet Transfer' => self::WALLET_TRANSFER->value,
            default => 0,
        };
    }

    /**
     * Finds and returns a PayoutMethod enum case from a given integer value.
     *
     * @param  int  $id  The integer value to find.
     *
     * @throws InvalidArgumentException if the id is not a valid enum case.
     */
    public static function fromId(int $id): self
    {
        return match ($id) {
            self::BANK_TRANSFER->value => self::BANK_TRANSFER,
            self::VIRTUAL_ACCOUNT_TRANSFER->value => self::VIRTUAL_ACCOUNT_TRANSFER,
            self::WALLET_TRANSFER->value => self::WALLET_TRANSFER,
            default => throw new InvalidArgumentException("Invalid payout method id: $id"),
        };
    }

    /**
     * Finds and returns a PayoutMethod enum case from a given integer value.
     *
     * @param  int  $value  The integer value to find.
     *
     * @throws ValueError if the value is not a valid enum case.
     */
    public static function fromStatusCode(int $value): self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        throw new ValueError("$value is not a valid backing value for enum ".self::class);
    }
}
