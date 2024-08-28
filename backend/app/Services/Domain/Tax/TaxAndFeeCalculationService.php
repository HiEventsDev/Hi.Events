<?php

namespace HiEvents\Services\Domain\Tax;

use HiEvents\DomainObjects\Enums\TaxCalculationType;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Services\Domain\Tax\DTO\TaxCalculationResponse;
use InvalidArgumentException;

class TaxAndFeeCalculationService
{
    private TaxAndFeeRollupService $taxRollupService;

    public function __construct(TaxAndFeeRollupService $taxRollupService)
    {
        $this->taxRollupService = $taxRollupService;
    }

    public function calculateTaxAndFeesForTicketPrice(
        TicketDomainObject      $ticket,
        TicketPriceDomainObject $price,
    ): TaxCalculationResponse
    {
        return $this->calculateTaxAndFeesForTicket($ticket, $price->getPrice());
    }

    public function calculateTaxAndFeesForTicket(
        TicketDomainObject $ticket,
        float              $price,
        int                $quantity = 1
    ): TaxCalculationResponse
    {
        $this->taxRollupService->resetRollUp();

        $fees = $ticket->getFees()
            ?->sum(fn($taxOrFee) => $this->calculateFee($taxOrFee, $price, $quantity)) ?: 0.00;

        $taxFees = $ticket->getTaxRates()
            ?->sum(fn($taxOrFee) => $this->calculateFee($taxOrFee, $price + $fees, $quantity));

        return new TaxCalculationResponse(
            feeTotal: $fees ? ($fees * $quantity) : 0.00,
            taxTotal: $taxFees ? ($taxFees * $quantity) : 0.00,
            rollUp: $this->taxRollupService->getRollUp()
        );
    }

    private function calculateFee(TaxAndFeesDomainObject $taxOrFee, float $price, int $quantity): float
    {
        $amount = match ($taxOrFee->getCalculationType()) {
            TaxCalculationType::FIXED->name => $taxOrFee->getRate(),
            TaxCalculationType::PERCENTAGE->name => ($price * $taxOrFee->getRate()) / 100,
            default => throw new InvalidArgumentException(__('Invalid calculation type')),
        };

        $this->taxRollupService->addToRollUp($taxOrFee, $amount * $quantity);

        return $amount;
    }
}
