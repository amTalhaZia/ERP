<?php

namespace Tests\Unit\Inventory;

use App\Services\Inventory\AverageCostCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AverageCostCalculatorTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_weighted_average(string $onHand, string $avg, string $qty, string $cost, string $expected): void
    {
        $this->assertSame($expected, (new AverageCostCalculator)->next($onHand, $avg, $qty, $cost));
    }

    /** @return array<string, array{string, string, string, string, string}> */
    public static function cases(): array
    {
        return [
            'first receipt takes incoming cost' => ['0', '0', '10', '5', '5.0000'],
            '10 @ 5 then 10 @ 7 averages to 6' => ['10', '5', '10', '7', '6.0000'],
            'uneven quantities' => ['3', '2', '1', '6', '3.0000'],
            'repeating decimal truncates at 4 dp' => ['2', '1', '1', '2', '1.3333'],
            'free goods dilute the average' => ['10', '4', '10', '0', '2.0000'],
            'negative on hand resets to incoming cost' => ['-2', '9', '5', '3', '3.0000'],
        ];
    }
}
