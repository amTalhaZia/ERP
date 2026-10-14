<?php

namespace App\Services\Inventory;

use App\Support\Money\Money;

/**
 * Weighted (moving) average cost: new_avg = (on_hand × avg + qty × unit_cost) / (on_hand + qty).
 * All inputs/outputs are DECIMAL(18,4) strings (bcmath via Money).
 * Documented limitation: FIFO/LIFO layers are not implemented.
 */
final class AverageCostCalculator
{
    public function next(string $onHand, string $currentAverage, string $quantityIn, string $unitCost): string
    {
        $onHand = Money::normalize($onHand);
        $currentAverage = Money::normalize($currentAverage);
        $quantityIn = Money::normalize($quantityIn);
        $unitCost = Money::normalize($unitCost);

        $newOnHand = Money::add($onHand, $quantityIn);

        // Nothing (or a negative balance) on hand: the incoming cost becomes the average.
        if (Money::compare($onHand, Money::zero()) <= 0 || Money::compare($newOnHand, Money::zero()) <= 0) {
            return $unitCost;
        }

        $existingValue = Money::mul($onHand, $currentAverage);
        $incomingValue = Money::mul($quantityIn, $unitCost);

        return Money::div(Money::add($existingValue, $incomingValue), $newOnHand);
    }
}
