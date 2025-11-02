<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Foundation\Application;

/**
 * Represents the types of processing methods for transactions.
 *
 * This enum helps in categorizing transactions based on how they are handled,
 * such as automatically by the system or manually by an administrator.
 */
enum MethodType: string
{
    case AUTOMATIC = 'auto';
    case MANUAL = 'manual';
    case ADMIN = 'admin';
    case EASYLINK_BANK_TRANSFER = 'easylink-bank-transfer';
    case EASYLINK_VIRTUAL_ACCOUNT_TRANSFER = 'easylink-va-transfer';
    case EASYLINK_E_WALLET_TRANSFER = 'easylink-ewallet-transfer';
    case SYSTEM = 'system';

    /**
     * Returns an array of all method types as string values.
     *
     * @return string[]
     */
    public static function types(): array
    {
        return array_map(fn (MethodType $type) => $type->value, self::cases());
    }

    /**
     * Returns a human-readable label for the method type.
     */
    public function label(): string|Application|Translator|null
    {
        return match ($this) {
            self::AUTOMATIC => __('Automatic Process'),
            self::MANUAL => __('Manual Process'),
            self::ADMIN => __('Admin Process'),
            self::EASYLINK_BANK_TRANSFER => __('Easylink Bank Transfer'),
            self::EASYLINK_VIRTUAL_ACCOUNT_TRANSFER => __('Easylink VA Transfer'),
            self::EASYLINK_E_WALLET_TRANSFER => __('Easylink E-Wallet Transfer'),
            self::SYSTEM => __('System Process'),
        };
    }

    /**
     * Returns the corresponding Bootstrap badge color class.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::AUTOMATIC => 'success',
            self::MANUAL => 'warning',
            self::ADMIN => 'primary',
            self::EASYLINK_BANK_TRANSFER,
            self::EASYLINK_VIRTUAL_ACCOUNT_TRANSFER,
            self::EASYLINK_E_WALLET_TRANSFER => 'info',
            self::SYSTEM => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Returns the corresponding Bootstrap text color class.
     */
    public function textColor(): string
    {
        return match ($this) {
            self::AUTOMATIC => 'text-success',
            self::MANUAL => 'text-warning',
            self::ADMIN => 'text-primary',
            self::EASYLINK_BANK_TRANSFER,
            self::EASYLINK_VIRTUAL_ACCOUNT_TRANSFER,
            self::EASYLINK_E_WALLET_TRANSFER => 'text-info',
            self::SYSTEM => 'text-secondary',
            default => 'text-secondary',
        };
    }
}
