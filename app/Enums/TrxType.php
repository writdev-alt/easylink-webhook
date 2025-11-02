<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Foundation\Application;
use ValueError;

/**
 * Represents the types of transactions in the system.
 *
 * This enum provides helper methods for getting human-readable labels,
 * CSS classes, and other properties for each transaction type.
 */
enum TrxType: string
{
    case DEPOSIT = 'deposit';
    case SEND_MONEY = 'send_money';
    case RECEIVE_MONEY = 'receive_money';
    case REQUEST_MONEY = 'request_money';
    case EXCHANGE_MONEY = 'exchange_money';
    case VOUCHER = 'voucher';
    case PAYMENT = 'payment';
    case RECEIVE_PAYMENT = 'receive_payment';
    case ADD_BALANCE = 'add_balance';
    case SUBTRACT_BALANCE = 'subtract_balance';
    case WITHDRAW = 'withdraw';
    case REFUND = 'refund';
    case REFERRAL_REWARD = 'referral_reward';
    case REWARD = 'reward';

    /**
     * Get all transaction types as an array for dropdowns.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $allowed = [
            self::DEPOSIT,
            self::WITHDRAW,
            self::RECEIVE_PAYMENT,
            self::EXCHANGE_MONEY,
        ];

        return array_combine(
            array_map(fn ($case) => $case->value, $allowed),
            array_map(fn ($case) => __(str_replace('_', ' ', ucfirst($case->value))), $allowed)
        );
    }

    /**
     * Returns the raw code/value of the enum case.
     */
    public function code(): string
    {
        return $this->value;
    }

    /**
     * Returns a human-readable label for the transaction type.
     */
    public function label(): string|Application|Translator|null
    {
        return match ($this) {
            self::DEPOSIT => __('Deposit'),
            self::SEND_MONEY => __('Send Money'),
            self::RECEIVE_MONEY => __('Receive Money'),
            self::REQUEST_MONEY => __('Request Money'),
            self::EXCHANGE_MONEY => __('Exchange Money'),
            self::VOUCHER => __('Voucher'),
            self::PAYMENT => __('Payment'),
            self::RECEIVE_PAYMENT => __('Receive Payment'),
            self::ADD_BALANCE => __('Add Balance'),
            self::SUBTRACT_BALANCE => __('Subtract Balance'),
            self::WITHDRAW => __('Withdraw'),
            self::REFUND => __('Refund'),
            self::REFERRAL_REWARD => __('Referral Reward'),
            self::REWARD => __('Reward'),
        };
    }

    /**
     * Convert the enum value to a kebab-case (hyphenated) string.
     */
    public function kebabCase(): string
    {
        return str_replace('_', '-', $this->value);
    }

    /**
     * Returns the badge color for the current transaction type.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::DEPOSIT, self::ADD_BALANCE, self::REWARD, self::REFERRAL_REWARD, self::REFUND => 'info',
            self::RECEIVE_MONEY, self::PAYMENT, self::RECEIVE_PAYMENT => 'success',
            self::REQUEST_MONEY => 'warning',
            self::WITHDRAW, self::SUBTRACT_BALANCE, self::SEND_MONEY => 'danger',
            self::EXCHANGE_MONEY, self::VOUCHER => 'primary',
            default => 'secondary',
        };
    }

    /**
     * Accepts a string or an array and returns the badge color.
     *
     * @param  string|array  $type  The transaction type value.
     * @return string The corresponding badge color.
     */
    public static function getBadgesColor(string|array $type): string
    {
        if (is_array($type)) {
            $type = $type[0];
        }

        try {
            return self::from($type)->badgeColor();
        } catch (ValueError) {
            return 'secondary';
        }
    }

    /**
     * Returns an array of transaction types that support user rank.
     *
     * @return array<self>
     */
    public static function userRankSupport(): array
    {
        return [
            self::DEPOSIT,
            self::SEND_MONEY,
            self::PAYMENT,
            self::REFERRAL_REWARD,
        ];
    }

    /**
     * Returns a string representing the icon for the transaction type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DEPOSIT => 'deposit',
            self::SEND_MONEY => 'send-money',
            self::RECEIVE_MONEY => 'receive-money',
            self::REQUEST_MONEY => 'request-money-1',
            self::EXCHANGE_MONEY => 'exchange-money',
            self::VOUCHER => 'voucher',
            self::PAYMENT => 'payment',
            self::RECEIVE_PAYMENT => 'receive-payment-1',
            self::ADD_BALANCE => 'add-balance',
            self::SUBTRACT_BALANCE => 'subtract-balance',
            self::WITHDRAW => 'withdraw',
            self::REFUND => 'refund',
            self::REFERRAL_REWARD => 'referral-reward',
            self::REWARD => 'reward',
            default => 'unknown',
        };
    }

    /**
     * Determines the cash flow direction for the transaction type.
     */
    public function cashFlow(): AmountFlow
    {
        return match ($this) {
            self::DEPOSIT,
            self::RECEIVE_MONEY,
            self::REQUEST_MONEY,
            self::RECEIVE_PAYMENT,
            self::REFERRAL_REWARD,
            self::REWARD,
            self::REFUND,
            self::ADD_BALANCE => AmountFlow::PLUS,

            self::SEND_MONEY,
            self::EXCHANGE_MONEY,
            self::PAYMENT,
            self::SUBTRACT_BALANCE,
            self::WITHDRAW => AmountFlow::MINUS,

            default => AmountFlow::DEFAULT, // Assuming a default case
        };
    }

    /**
     * Determines the transaction processing type.
     *
     * @return MethodType
     */
    public function processingType()
    {
        return match ($this) {
            self::DEPOSIT,
            self::RECEIVE_MONEY,
            self::REQUEST_MONEY,
            self::RECEIVE_PAYMENT,
            self::SEND_MONEY,
            self::EXCHANGE_MONEY,
            self::PAYMENT => MethodType::AUTOMATIC,

            self::ADD_BALANCE,
            self::REFUND,
            self::WITHDRAW => MethodType::AUTOMATIC,

            self::REFERRAL_REWARD,
            self::REWARD,
            self::SUBTRACT_BALANCE => MethodType::SYSTEM,

            default => MethodType::SYSTEM
        };
    }
}
