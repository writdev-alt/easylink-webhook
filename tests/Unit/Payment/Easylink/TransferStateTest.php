<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Easylink;

use App\Payment\Easylink\Enums\TransferState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ValueError;

#[CoversClass(TransferState::class)]
class TransferStateTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function test_from_status_code_returns_expected_case(int $value, TransferState $expected): void
    {
        $this->assertSame($expected, TransferState::fromStatusCode($value));
    }

    public static function statusProvider(): array
    {
        return [
            'create' => [TransferState::CREATE->value, TransferState::CREATE],
            'confirm' => [TransferState::CONFIRM->value, TransferState::CONFIRM],
            'hold' => [TransferState::hold->value, TransferState::hold],
            'review' => [TransferState::REVIEW->value, TransferState::REVIEW],
            'payout' => [TransferState::PAYOUT->value, TransferState::PAYOUT],
            'sent' => [TransferState::SENT->value, TransferState::SENT],
            'complete' => [TransferState::COMPLETE->value, TransferState::COMPLETE],
            'canceled' => [TransferState::CANCELED->value, TransferState::CANCELED],
            'failed' => [TransferState::FAILED->value, TransferState::FAILED],
            'refund success' => [TransferState::REFUND_SUCCESS->value, TransferState::REFUND_SUCCESS],
            'processing bank partner' => [TransferState::PROCESSING_BANK_PARTNER->value, TransferState::PROCESSING_BANK_PARTNER],
            'remind recipient' => [TransferState::REMIND_RECIPIENT->value, TransferState::REMIND_RECIPIENT],
        ];
    }

    public function test_from_status_code_throws_for_invalid_value(): void
    {
        $this->expectException(ValueError::class);

        TransferState::fromStatusCode(-1);
    }

    #[DataProvider('labelProvider')]
    public function test_label_returns_expected_translation(TransferState $state, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $state->label());
    }

    public static function labelProvider(): array
    {
        return [
            'create' => [TransferState::CREATE, 'Created'],
            'confirm' => [TransferState::CONFIRM, 'Confirmed'],
            'hold' => [TransferState::hold, 'Hold'],
            'review' => [TransferState::REVIEW, 'Under Review'],
            'payout' => [TransferState::PAYOUT, 'Payout'],
            'sent' => [TransferState::SENT, 'Sent'],
            'complete' => [TransferState::COMPLETE, 'Completed'],
            'canceled' => [TransferState::CANCELED, 'Canceled'],
            'failed' => [TransferState::FAILED, 'Failed'],
            'refund success' => [TransferState::REFUND_SUCCESS, 'Refund Successful'],
            'processing bank partner' => [TransferState::PROCESSING_BANK_PARTNER, 'Processing Bank Partner'],
            'remind recipient' => [TransferState::REMIND_RECIPIENT, 'Remind Recipient'],
        ];
    }
}


