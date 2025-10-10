<?php

namespace HiEvents\Services\Domain\Tax;

use HiEvents\DomainObjects\Enums\TaxCalculationType;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Services\Domain\Tax\DTO\TaxCalculationResponse;
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
    ): TaxCalculationResponse
    {
        return $this->calculateTaxAndFeesForProduct($product, $price->getPrice());
    }

    public function calculateTaxAndFeesForProduct(
        ProductDomainObject $product,
        float               $price,
        int                 $quantity = 1
    ): TaxCalculationResponse
    {
        $this->taxRollupService->resetRollUp();

        $fees = $product->getFees()
            ?->sum(fn($taxOrFee) => $this->calculateFee($taxOrFee, $price, $quantity)) ?: 0.00;

        $taxFees = $product->getTaxRates()
            ?->sum(fn($taxOrFee) => $this->calculateFee($taxOrFee, $price + $fees, $quantity));

        return new TaxCalculationResponse(
            feeTotal: $fees ? ($fees * $quantity) : 0.00,
            taxTotal: $taxFees ? ($taxFees * $quantity) : 0.00,
            rollUp: $this->taxRollupService->getRollUp()
        );
    }

    private function calculateFee(TaxAndFeesDomainObject $taxOrFee, float $price, int $quantity): float
    {
        // We do not charge a tax or fee on items which are free of charge
        if ($price === 0.00) {
            $this->taxRollupService->addToRollUp($taxOrFee, 0);

            return 0.00;
        }

        $amount = match ($taxOrFee->getCalculationType()) {
            TaxCalculationType::FIXED->name => $taxOrFee->getRate(),
            TaxCalculationType::PERCENTAGE->name => ($price * $taxOrFee->getRate()) / 100,
            default => throw new InvalidArgumentException(__('Invalid calculation type')),
        };

        $this->taxRollupService->addToRollUp($taxOrFee, $amount * $quantity);

        return $amount;
    }
}
