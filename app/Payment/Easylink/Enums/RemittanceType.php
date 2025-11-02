<?php

declare(strict_types=1);

namespace App\Payment\Easylink\Enums;

/**
 * Represents the type of remittance.
 *
 * This enum provides a clear and type-safe way to define whether a transaction
 * is a domestic or international remittance, along with helper methods for labels.
 */
enum RemittanceType: string
{
    case DOMESTIC = 'domestic';
    case INTERNATIONAL = 'international';

    /**
     * Get a human-readable label for the remittance type.
     */
    public function label(): string
    {
        return match ($this) {
            self::DOMESTIC => 'Domestic',
            self::INTERNATIONAL => 'International',
        };
    }
}
