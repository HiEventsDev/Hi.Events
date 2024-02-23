<?php

namespace TicketKitten\Service\Common\Tax;

use InvalidArgumentException;
use TicketKitten\DomainObjects\Enums\TaxCalculationType;
use TicketKitten\DomainObjects\Enums\TicketType;
use TicketKitten\DomainObjects\TaxAndFeesDomainObject;
use TicketKitten\DomainObjects\TicketDomainObject;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Service\Common\Tax\DTO\TaxCalculationResponse;

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

        if ($ticket->getType() === TicketType::FREE->name) {
            return new TaxCalculationResponse(
                feeTotal: 0.00,
                taxTotal: 0.00,
                rollUp: $this->taxRollupService->getRollUp()
            );
        }

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
