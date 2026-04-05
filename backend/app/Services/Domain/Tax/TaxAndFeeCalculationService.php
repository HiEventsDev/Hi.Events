<?php

namespace HiEvents\Services\Domain\Tax;

use HiEvents\DomainObjects\Enums\TaxCalculationType;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Services\Domain\Tax\DTO\TaxCalculationResponse;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TaxAndFeeCalculationService
{
    private TaxAndFeeRollupService $taxRollupService;

    public function __construct(TaxAndFeeRollupService $taxRollupService)
    {
        $this->taxRollupService = $taxRollupService;
    }

    public function calculateTaxAndFeesForProductPrice(
        ProductDomainObject      $product,
        ProductPriceDomainObject $price,
        bool                     $excludeOnlineOnly = false,
    ): TaxCalculationResponse
    {
        return $this->calculateTaxAndFeesForProduct($product, $price->getPrice(), excludeOnlineOnly: $excludeOnlineOnly);
    }

    public function calculateTaxAndFeesForProduct(
        ProductDomainObject $product,
        float               $price,
        int                 $quantity = 1,
        bool                $excludeOnlineOnly = false,
    ): TaxCalculationResponse
    {
        $this->taxRollupService->resetRollUp();

        $fees = $product->getFees()
            ?->reject(fn($taxOrFee) => $excludeOnlineOnly && $taxOrFee->getIsOnlineOnly())
            ?->reject(fn($taxOrFee) => $taxOrFee->isPerOrder())
            ?->sum(fn($taxOrFee) => $this->calculateFee($taxOrFee, $price, $quantity)) ?: 0.00;

        $inclusiveTaxPerUnit = 0.00;

        $taxFees = $product->getTaxRates()
            ?->reject(fn($taxOrFee) => $excludeOnlineOnly && $taxOrFee->getIsOnlineOnly())
            ?->reject(fn($taxOrFee) => $taxOrFee->isPerOrder())
            ?->sum(function ($taxOrFee) use ($price, $fees, $quantity, &$inclusiveTaxPerUnit) {
                $amount = $this->calculateFee($taxOrFee, $price + $fees, $quantity);
                if ($taxOrFee->getIsTaxInclusive()) {
                    $inclusiveTaxPerUnit += $amount;
                }
                return $amount;
            });

        return new TaxCalculationResponse(
            feeTotal: $fees ? ($fees * $quantity) : 0.00,
            taxTotal: $taxFees ? ($taxFees * $quantity) : 0.00,
            rollUp: $this->taxRollupService->getRollUp(),
            inclusiveTaxTotal: $inclusiveTaxPerUnit ? ($inclusiveTaxPerUnit * $quantity) : 0.00,
        );
    }

    /**
     * Calculate per-order fees/taxes that should be applied once per order.
     * Collects unique per-order fees from all products in the order.
     *
     * @param Collection<ProductDomainObject> $products
     * @param float $orderSubtotal The total before per-order additions
     * @return TaxCalculationResponse
     */
    public function calculatePerOrderFees(Collection $products, float $orderSubtotal): TaxCalculationResponse
    {
        $this->taxRollupService->resetRollUp();

        $seenFeeIds = [];
        $perOrderFees = collect();
        $perOrderTaxes = collect();

        foreach ($products as $product) {
            $product->getFees()?->each(function ($fee) use (&$seenFeeIds, $perOrderFees) {
                if ($fee->isPerOrder() && !in_array($fee->getId(), $seenFeeIds)) {
                    $seenFeeIds[] = $fee->getId();
                    $perOrderFees->push($fee);
                }
            });
            $product->getTaxRates()?->each(function ($tax) use (&$seenFeeIds, $perOrderTaxes) {
                if ($tax->isPerOrder() && !in_array($tax->getId(), $seenFeeIds)) {
                    $seenFeeIds[] = $tax->getId();
                    $perOrderTaxes->push($tax);
                }
            });
        }

        $feeTotal = $perOrderFees->sum(fn($fee) => $this->calculateFee($fee, $orderSubtotal, 1));

        $inclusiveTaxTotal = 0.00;

        $taxTotal = $perOrderTaxes->sum(function ($tax) use ($orderSubtotal, $feeTotal, &$inclusiveTaxTotal) {
            $amount = $this->calculateFee($tax, $orderSubtotal + $feeTotal, 1);
            if ($tax->getIsTaxInclusive()) {
                $inclusiveTaxTotal += $amount;
            }
            return $amount;
        });

        return new TaxCalculationResponse(
            feeTotal: $feeTotal,
            taxTotal: $taxTotal,
            rollUp: $this->taxRollupService->getRollUp(),
            inclusiveTaxTotal: $inclusiveTaxTotal,
        );
    }

    private function calculateFee(TaxAndFeesDomainObject $taxOrFee, float $price, int $quantity): float
    {
        // We do not charge a tax or fee on items which are free of charge
        if ($price === 0.00) {
            $this->taxRollupService->addToRollUp($taxOrFee, 0);

            return 0.00;
        }

        $isInclusive = $taxOrFee->getIsTaxInclusive();

        $amount = match ($taxOrFee->getCalculationType()) {
            TaxCalculationType::FIXED->name => $taxOrFee->getRate(),
            TaxCalculationType::PERCENTAGE->name => $isInclusive
                ? ($price * $taxOrFee->getRate()) / (100 + $taxOrFee->getRate())
                : ($price * $taxOrFee->getRate()) / 100,
            default => throw new InvalidArgumentException(__('Invalid calculation type')),
        };

        $this->taxRollupService->addToRollUp($taxOrFee, $amount * $quantity);

        return $amount;
    }
}
