<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Services\Domain\Order\DTO\OrderItemPricingLineDTO;
use HiEvents\Services\Domain\Order\DTO\OrderLineDiscountAllocationDTO;
use Illuminate\Support\Collection;

class OrderDiscountAllocationService
{
    /**
     * @param  Collection<int, OrderItemPricingLineDTO>  $lines
     * @return array<int, array<int, OrderLineDiscountAllocationDTO>>
     */
    public function allocate(Collection $lines, PromoCodeDomainObject $promoCode, string $currency): array
    {
        $multiplier = Currency::isZeroDecimalCurrency($currency) ? 1 : 100;

        $eligibleLines = [];
        foreach ($lines as $index => $line) {
            if ($this->isEligible($line, $promoCode)) {
                $eligibleLines[$index] = [
                    'unitMinor' => (int) round($line->prices->price * $multiplier),
                    'quantity' => $line->product_price->quantity,
                ];
            }
        }

        $subtotalMinor = 0;
        foreach ($eligibleLines as $eligibleLine) {
            $subtotalMinor += $eligibleLine['unitMinor'] * $eligibleLine['quantity'];
        }

        if ($subtotalMinor === 0) {
            return $this->toAllocations($lines, [], [], $multiplier);
        }

        $targetMinor = min((int) round($promoCode->getDiscount() * $multiplier), $subtotalMinor);

        $perUnitMinor = [];
        $remainingMinor = $targetMinor;
        foreach ($eligibleLines as $index => $eligibleLine) {
            $perUnitMinor[$index] = intdiv($targetMinor * $eligibleLine['unitMinor'], $subtotalMinor);
            $remainingMinor -= $perUnitMinor[$index] * $eligibleLine['quantity'];
        }

        $splitUnits = [];
        while ($remainingMinor > 0) {
            $index = $this->nextLineToIncrement($eligibleLines, $perUnitMinor, $remainingMinor, $targetMinor, $subtotalMinor);

            if ($index === null) {
                $index = $this->lineToSplit($eligibleLines, $perUnitMinor);
                $splitUnits[$index] = $remainingMinor;
                break;
            }

            $perUnitMinor[$index]++;
            $remainingMinor -= $eligibleLines[$index]['quantity'];
        }

        return $this->toAllocations($lines, $perUnitMinor, $splitUnits, $multiplier);
    }

    private function isEligible(OrderItemPricingLineDTO $line, PromoCodeDomainObject $promoCode): bool
    {
        return $promoCode->appliesToProduct($line->product)
            && ! $line->product->isFreeType()
            && ! $line->product->isDonationType()
            && $line->prices->price > 0;
    }

    /**
     * @param  array<int, array{unitMinor: int, quantity: int}>  $eligibleLines
     * @param  array<int, int>  $perUnitMinor
     */
    private function nextLineToIncrement(
        array $eligibleLines,
        array $perUnitMinor,
        int $remainingMinor,
        int $targetMinor,
        int $subtotalMinor,
    ): ?int {
        $bestIndex = null;
        $bestFraction = -1;

        foreach ($eligibleLines as $index => $eligibleLine) {
            if ($perUnitMinor[$index] >= $eligibleLine['unitMinor'] || $eligibleLine['quantity'] > $remainingMinor) {
                continue;
            }

            $fraction = ($targetMinor * $eligibleLine['unitMinor']) % $subtotalMinor;

            if ($fraction > $bestFraction) {
                $bestFraction = $fraction;
                $bestIndex = $index;
            }
        }

        return $bestIndex;
    }

    /**
     * @param  array<int, array{unitMinor: int, quantity: int}>  $eligibleLines
     * @param  array<int, int>  $perUnitMinor
     */
    private function lineToSplit(array $eligibleLines, array $perUnitMinor): int
    {
        $splitIndex = null;
        $smallestQuantity = null;

        foreach ($eligibleLines as $index => $eligibleLine) {
            if ($perUnitMinor[$index] >= $eligibleLine['unitMinor']) {
                continue;
            }

            if ($smallestQuantity === null || $eligibleLine['quantity'] < $smallestQuantity) {
                $smallestQuantity = $eligibleLine['quantity'];
                $splitIndex = $index;
            }
        }

        return $splitIndex;
    }

    /**
     * @param  Collection<int, OrderItemPricingLineDTO>  $lines
     * @param  array<int, int>  $perUnitMinor
     * @param  array<int, int>  $splitUnits
     * @return array<int, array<int, OrderLineDiscountAllocationDTO>>
     */
    private function toAllocations(Collection $lines, array $perUnitMinor, array $splitUnits, int $multiplier): array
    {
        $allocations = [];

        foreach ($lines as $index => $line) {
            $quantity = $line->product_price->quantity;
            $unitMinorDiscount = $perUnitMinor[$index] ?? 0;

            if (isset($splitUnits[$index])) {
                $allocations[$index] = [
                    new OrderLineDiscountAllocationDTO(
                        per_unit_discount: ($unitMinorDiscount + 1) / $multiplier,
                        quantity: $splitUnits[$index],
                    ),
                    new OrderLineDiscountAllocationDTO(
                        per_unit_discount: $unitMinorDiscount / $multiplier,
                        quantity: $quantity - $splitUnits[$index],
                    ),
                ];

                continue;
            }

            $allocations[$index] = [
                new OrderLineDiscountAllocationDTO(per_unit_discount: $unitMinorDiscount / $multiplier, quantity: $quantity),
            ];
        }

        return $allocations;
    }
}
