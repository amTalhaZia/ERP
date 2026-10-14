<?php

namespace Tests\Unit\Support;

use App\Support\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_add_avoids_floating_point_drift(): void
    {
        $this->assertSame('0.3000', Money::add('0.1', '0.2'));
    }

    public function test_sum_of_many_small_amounts_is_exact(): void
    {
        $this->assertSame('1.0000', Money::sum(array_fill(0, 10, '0.1')));
    }

    public function test_percent_calculates_tax(): void
    {
        $this->assertSame('15.0000', Money::percent('200', '7.5'));
    }

    #[DataProvider('roundingCases')]
    public function test_round_is_half_up(string $input, string $expected): void
    {
        $this->assertSame($expected, Money::round($input, 2));
    }

    /** @return array<string, array{string, string}> */
    public static function roundingCases(): array
    {
        return [
            'rounds up at half' => ['2.345', '2.35'],
            'rounds down below half' => ['2.344', '2.34'],
            'negative rounds away from zero at half' => ['-2.345', '-2.35'],
            'already exact' => ['10.00', '10.00'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_normalize_rejects_garbage(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::normalize($input);
    }

    /** @return array<string, array{string}> */
    public static function invalidAmounts(): array
    {
        return ['letters' => ['abc'], 'sql' => ["1; DROP"], 'double dot' => ['1.2.3']];
    }

    public function test_normalize_accepts_thousands_separators(): void
    {
        $this->assertSame('1234567.8900', Money::normalize('1,234,567.89'));
    }

    public function test_div_by_zero_throws(): void
    {
        $this->expectException(\DivisionByZeroError::class);
        Money::div('1', '0');
    }
}
