<?php

declare(strict_types=1);

namespace App\Payment\Easylink\Enums;

use ValueError;

/**
 * Represents the various states of an EasyLink transfer transaction.
 *
 * This enum provides a clear and type-safe way to handle and translate
 * the numeric state codes received from the EasyLink API into human-readable labels.
 */
enum TransferState: int
{
    case CREATE = 1;
    case CONFIRM = 2;
    case HOLD = 3;
    case REVIEW = 4;
    case PAYOUT = 5;
    case SENT = 6;
    case COMPLETE = 7;
    case CANCELED = 8;
    case FAILED = 9;
    case REFUND_SUCCESS = 10;
    case PROCESSING_BANK_PARTNER = 26;
    case REMIND_RECIPIENT = 27;

    /**
     * Get a human-readable label for the transfer state.
     */
    public function label(): string
    {
        return match ($this) {
            self::CREATE => 'Created',
            self::CONFIRM => 'Confirmed',
            self::HOLD => 'HOLD',
            self::REVIEW => 'Under Review',
            self::PAYOUT => 'Payout',
            self::SENT => 'Sent',
            self::COMPLETE => 'Completed',
            self::CANCELED => 'Canceled',
            self::FAILED => 'Failed',
            self::REFUND_SUCCESS => 'Refund Successful',
            self::PROCESSING_BANK_PARTNER => 'Processing Bank Partner',
            self::REMIND_RECIPIENT => 'Remind Recipient',
        };
    }

    /**
     * Finds and returns a TransferState enum case from a given value.
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
