<?php

namespace Tests\Feature\Sales;

use App\Services\Sales\DocumentTotals;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test (no framework boot) for the shared quotation/order totals.
 */
class DocumentTotalsTest extends TestCase
{
    public function test_line_applies_discount_then_tax(): void
    {
        $line = (new DocumentTotals)->line('2', '100', '10', '15');

        $this->assertSame('200.0000', $line['gross']);
        $this->assertSame('20.0000', $line['discount_amount']);
        $this->assertSame('180.0000', $line['line_subtotal']);
        $this->assertSame('27.0000', $line['tax_amount']);
        $this->assertSame('207.0000', $line['line_total']);
    }

    public function test_document_totals_sum_lines_exactly(): void
    {
        $totals = (new DocumentTotals)->compute([
            ['description' => 'A', 'quantity' => '2', 'unit_price' => '100', 'discount_percent' => '10', 'tax_rate' => '15'],
            ['description' => 'B', 'quantity' => '3', 'unit_price' => '0.1', 'tax_rate' => '0'],
            ['description' => 'C', 'quantity' => '1.5', 'unit_price' => '19.99', 'discount_percent' => '0', 'tax_rate' => '7.5'],
        ]);

        // C: 29.985 subtotal, tax 2.2488 (bcmath truncation at 4dp)
        $this->assertSame('210.2850', $totals['subtotal']);
        $this->assertSame('20.0000', $totals['discount_total']);
        $this->assertSame('29.2488', $totals['tax_total']);
        $this->assertSame('239.5338', $totals['total']);
        $this->assertSame([1, 2, 3], array_column($totals['lines'], 'line_no'));
        $this->assertSame('0.3000', $totals['lines'][1]['line_total']);
    }

    public function test_lines_are_numbered_in_input_key_order(): void
    {
        $totals = (new DocumentTotals)->compute([
            1 => ['description' => 'second', 'quantity' => '1', 'unit_price' => '2', 'product_id' => 9],
            0 => ['description' => 'first', 'quantity' => '1', 'unit_price' => '1'],
        ]);

        $this->assertSame(['first', 'second'], array_column($totals['lines'], 'description'));
        $this->assertSame([1, 2], array_column($totals['lines'], 'line_no'));
    }

    public function test_empty_document_is_zero(): void
    {
        $totals = (new DocumentTotals)->compute([]);

        $this->assertSame('0.0000', $totals['total']);
        $this->assertSame([], $totals['lines']);
    }
}
