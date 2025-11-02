<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the status of a transaction in the system.
 *
 * This enum provides a detailed set of statuses to track a transaction's lifecycle,
 * along with helper methods for displaying UI components like labels, icons, and colors.
 */
enum TrxStatus: string
{
    // Awaiting initial action or confirmation.
    case PENDING = 'pending';
    // The transaction is awaiting processing by a financial institution (bank).
    case AWAITING_FI_PROCESS = 'awaiting_fi_process';
    // The transaction is awaiting processing by a payment gateway.
    case AWAITING_PG_PROCESS = 'awaiting_pg_process';
    // The transaction is awaiting an action from the user (e.g., payment confirmation).
    case AWAITING_USER_ACTION = 'awaiting_user_action';
    // The transaction requires approval from an administrator.
    case AWAITING_ADMIN_APPROVAL = 'awaiting_admin_approval';
    // The transaction was successful and funds have been settled.
    case COMPLETED = 'completed';
    // The transaction was manually or automatically canceled.
    case CANCELED = 'canceled';
    // The transaction failed due to an error.
    case FAILED = 'failed';
    // The transaction was reversed or refunded.
    case REFUNDED = 'refunded';
    // The transaction was expired
    case EXPIRED = 'expired';

    /**
     * Returns a human-readable label for the transaction status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::AWAITING_FI_PROCESS => __('Awaiting FI Process'),
            self::AWAITING_PG_PROCESS => __('Awaiting Payment Gateway Process'),
            self::AWAITING_USER_ACTION => __('Awaiting User Action'),
            self::AWAITING_ADMIN_APPROVAL => __('Awaiting Admin Approval'),
            self::COMPLETED => __('Completed'),
            self::CANCELED => __('Canceled'),
            self::FAILED => __('Failed'),
            self::REFUNDED => __('Refunded'),
            self::EXPIRED => __('Expired')
        };
    }

    /**
     * Returns all transaction statuses as an associative array for use in dropdowns.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }

    /**
     * Get the icon associated with the transaction status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'fa-regular fa-clock',
            self::AWAITING_FI_PROCESS, self::AWAITING_PG_PROCESS => 'fa-solid fa-spinner fa-spin-pulse',
            self::AWAITING_USER_ACTION => 'fa-solid fa-hourglass-half',
            self::AWAITING_ADMIN_APPROVAL => 'fa-solid fa-user-shield',
            self::COMPLETED => 'fa-solid fa-check',
            self::CANCELED => 'fa-solid fa-xmark',
            self::FAILED => 'fa-solid fa-circle-exclamation',
            self::REFUNDED => 'fa-solid fa-rotate-left',
            self::EXPIRED => 'fa-solid fa-hourglass-half',
        };
    }

    /**
     * Get the color class associated with the transaction status.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING, self::AWAITING_USER_ACTION, self::AWAITING_ADMIN_APPROVAL => 'warning',
            self::AWAITING_FI_PROCESS, self::AWAITING_PG_PROCESS => 'info',
            self::COMPLETED => 'success',
            self::CANCELED, self::FAILED, self::EXPIRED => 'danger',
            self::REFUNDED => 'primary',
        };
    }

    /**
     * Get the hexadecimal color code associated with the transaction status.
     */
    public function colorCode(): string
    {
        return match ($this) {
            self::PENDING, self::AWAITING_USER_ACTION, self::AWAITING_ADMIN_APPROVAL => '#ffc107',
            self::AWAITING_FI_PROCESS, self::AWAITING_PG_PROCESS => '#17a2b8',
            self::COMPLETED => '#28a745',
            self::CANCELED, self::FAILED, self::EXPIRED => '#dc3545',
            self::REFUNDED => '#007bff',
        };
    }

    /**
     * Get status from string safely
     */
    public static function fromSafe(string $value): ?self
    {
        try {
            return self::from($value);
        } catch (\ValueError) {
            return null;
        }
    }
}
