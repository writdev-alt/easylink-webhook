<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Easylink;

use App\Payment\Easylink\Enums\RemittanceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(RemittanceType::class)]
class RemittanceTypeTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function test_label_returns_expected_translation(RemittanceType $type, string $expected): void
    {
        $this->assertSame($expected, $type->label());
    }

    public static function labelProvider(): array
    {
        return [
            'domestic' => [RemittanceType::DOMESTIC, 'Domestic'],
            'international' => [RemittanceType::INTERNATIONAL, 'International'],
        ];
    }

    public function test_enum_values(): void
    {
        $this->assertSame('domestic', RemittanceType::DOMESTIC->value);
        $this->assertSame('international', RemittanceType::INTERNATIONAL->value);
    }
}
