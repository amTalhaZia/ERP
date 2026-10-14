<?php

namespace Tests\Feature\Procurement;

use App\Services\Procurement\DocumentTotals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Pure calculation tests (no database). */
class DocumentTotalsTest extends TestCase
{
    public function test_line_without_discount_applies_tax_on_the_subtotal(): void
    {
        $line = (new DocumentTotals)->line('3', '50', '0', '10');

        $this->assertSame('150.0000', $line['line_subtotal']);
        $this->assertSame('15.0000', $line['tax_amount']);
        $this->assertSame('165.0000', $line['line_total']);
        $this->assertSame('0.0000', $line['discount_amount']);
    }

    public function test_discount_is_applied_before_tax(): void
    {
        $line = (new DocumentTotals)->line('4', '25', '10', '5');

        $this->assertSame('100.0000', $line['line_gross']);
        $this->assertSame('10.0000', $line['discount_amount']);
        $this->assertSame('90.0000', $line['line_subtotal']);
        $this->assertSame('4.5000', $line['tax_amount']);
        $this->assertSame('94.5000', $line['line_total']);
    }

    public function test_document_sums_lines_and_adds_shipping(): void
    {
        $totals = (new DocumentTotals)->document([
            ['quantity' => '3', 'unit_price' => '50', 'tax_rate' => '10'],
            ['quantity' => '4', 'unit_price' => '25', 'discount_percent' => '10', 'tax_rate' => '5'],
            ['quantity' => '0.5', 'unit_price' => '19.99'],
        ], '12.50');

        $this->assertSame('249.9950', $totals['subtotal']);
        $this->assertSame('10.0000', $totals['discount_total']);
        $this->assertSame('19.5000', $totals['tax_total']);
        $this->assertSame('12.5000', $totals['shipping_cost']);
        $this->assertSame('281.9950', $totals['total']);
        $this->assertCount(3, $totals['lines']);
    }

    /** @return array<string, array{string, string, string, string, string}> */
    public static function floatTraps(): array
    {
        return [
            'tenths' => ['0.1', '0.2', '0', '0', '0.0200'],
            'repeating tax' => ['1', '33.33', '0', '7.5', '35.8297'],
            'large' => ['1000000', '999.9999', '0', '0', '999999900.0000'],
        ];
    }

    #[DataProvider('floatTraps')]
    public function test_amounts_are_exact_decimal_strings(string $qty, string $price, string $discount, string $tax, string $expectedTotal): void
    {
        $this->assertSame($expectedTotal, (new DocumentTotals)->line($qty, $price, $discount, $tax)['line_total']);
    }

    public function test_empty_document_totals_are_zero(): void
    {
        $totals = (new DocumentTotals)->document([]);

        $this->assertSame('0.0000', $totals['total']);
        $this->assertSame([], $totals['lines']);
    }
}
