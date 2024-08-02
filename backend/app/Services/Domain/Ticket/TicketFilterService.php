<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\Constants;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use HiEvents\Services\Domain\Ticket\DTO\AvailableTicketQuantitiesDTO;
use Illuminate\Support\Collection;

class TicketFilterService
{
    public function __construct(
        private readonly TaxAndFeeCalculationService           $taxCalculationService,
        private readonly TicketPriceService                    $ticketPriceService,
        private readonly AvailableTicketQuantitiesFetchService $fetchAvailableTicketQuantitiesService,
    )
    {
    }

    /**
     * @param Collection<TicketDomainObject> $tickets
     * @param PromoCodeDomainObject|null $promoCode
     * @param bool $hideSoldOutTickets
     * @return Collection<TicketDomainObject>
     */
    public function filter(
        Collection             $tickets,
        ?PromoCodeDomainObject $promoCode = null,
        bool                   $hideSoldOutTickets = true,
    ): Collection
    {
        if ($tickets->isEmpty()) {
            return $tickets;
        }

        $ticketQuantities = $this->fetchAvailableTicketQuantitiesService
            ->getAvailableTicketQuantities($tickets->first()->getEventId());

        return $tickets
            ->map(fn(TicketDomainObject $ticket) => $this->processTicket($ticket, $ticketQuantities->ticketQuantities, $promoCode))
            ->reject(fn(TicketDomainObject $ticket) => $this->filterTicket($ticket, $promoCode, $hideSoldOutTickets))
            ->each(fn(TicketDomainObject $ticket) => $this->processTicketPrices($ticket, $hideSoldOutTickets));
    }

    private function isHiddenByPromoCode(TicketDomainObject $ticket, ?PromoCodeDomainObject $promoCode): bool
    {
        return $ticket->getIsHiddenWithoutPromoCode() && !(
                $promoCode
                && $promoCode->appliesToTicket($ticket)
            );
    }

    private function shouldTicketBeDiscounted(?PromoCodeDomainObject $promoCode, TicketDomainObject $ticket): bool
    {
        if ($ticket->isDonationType() || $ticket->isFreeType()) {
            return false;
        }

        return $promoCode
            && $promoCode->isDiscountCode()
            && $promoCode->appliesToTicket($ticket);
    }

    /**
     * @param PromoCodeDomainObject|null $promoCode
     * @param TicketDomainObject $ticket
     * @param Collection<AvailableTicketQuantitiesDTO> $ticketQuantities
     * @return TicketDomainObject
     */
    private function processTicket(
        TicketDomainObject     $ticket,
        Collection             $ticketQuantities,
        ?PromoCodeDomainObject $promoCode = null,
    ): TicketDomainObject
    {
        if ($this->shouldTicketBeDiscounted($promoCode, $ticket)) {
            $ticket->getTicketPrices()?->each(function (TicketPriceDomainObject $price) use ($ticket, $promoCode) {
                $price->setPriceBeforeDiscount($price->getPrice());
                $price->setPrice($this->ticketPriceService->getIndividualPrice($ticket, $price, $promoCode));
            });
        }

        $ticket->getTicketPrices()?->map(function (TicketPriceDomainObject $price) use ($ticketQuantities) {
            $availableQuantity = $ticketQuantities->where('price_id', $price->getId())->first()?->quantity_available;
            $availableQuantity = $availableQuantity === Constants::INFINITE ? null : $availableQuantity;
            $price->setQuantityAvailable(
                max($availableQuantity, 0)
            );
        });

        return $ticket;
    }

    private function filterTicket(
        TicketDomainObject     $ticket,
        ?PromoCodeDomainObject $promoCode = null,
        bool                   $hideSoldOutTickets = true,
    ): bool
    {
        $hidden = false;

        if ($this->isHiddenByPromoCode($ticket, $promoCode)) {
            $ticket->setOffSaleReason(__('Ticket is hidden without promo code'));
            $hidden = true;
        }

        if ($ticket->isSoldOut() && $ticket->getHideWhenSoldOut()) {
            $ticket->setOffSaleReason(__('Ticket is sold out'));
            $hidden = true;
        }

        if ($ticket->isBeforeSaleStartDate() && $ticket->getHideBeforeSaleStartDate()) {
            $ticket->setOffSaleReason(__('Ticket is before sale start date'));
            $hidden = true;
        }

        if ($ticket->isAfterSaleEndDate() && $ticket->getHideAfterSaleEndDate()) {
            $ticket->setOffSaleReason(__('Ticket is after sale end date'));
            $hidden = true;
        }

        if ($ticket->getIsHidden()) {
            $ticket->setOffSaleReason(__('Ticket is hidden'));
            $hidden = true;
        }

        return $hidden && $hideSoldOutTickets;
    }

    private function processTicketPrice(TicketDomainObject $ticket, TicketPriceDomainObject $price): void
    {
        $taxAndFees = $this->taxCalculationService
            ->calculateTaxAndFeesForTicketPrice($ticket, $price);

        $price
            ->setTaxTotal(Currency::round($taxAndFees->taxTotal))
            ->setFeeTotal(Currency::round($taxAndFees->feeTotal));

        $price->setIsAvailable($this->getPriceAvailability($price, $ticket));
    }

    private function filterTicketPrice(
        TicketDomainObject      $ticket,
        TicketPriceDomainObject $price,
        bool                    $hideSoldOutTickets = true
    ): bool
    {
        $hidden = false;

        if (!$ticket->isTieredType()) {
            return false;
        }

        if ($price->isBeforeSaleStartDate() && $ticket->getHideBeforeSaleStartDate()) {
            $price->setOffSaleReason(__('Price is before sale start date'));
            $hidden = true;
        }

        if ($price->isAfterSaleEndDate() && $ticket->getHideAfterSaleEndDate()) {
            $price->setOffSaleReason(__('Price is after sale end date'));
            $hidden = true;
        }

        if ($price->isSoldOut() && $ticket->getHideWhenSoldOut()) {
            $price->setOffSaleReason(__('Price is sold out'));
            $hidden = true;
        }

        if ($price->getIsHidden()) {
            $price->setOffSaleReason(__('Price is hidden'));
            $hidden = true;
        }

        return $hidden && $hideSoldOutTickets;
    }

    private function processTicketPrices(TicketDomainObject $ticket, bool $hideSoldOutTickets = true): void
    {
        $ticket->setTicketPrices(
            $ticket->getTicketPrices()
                ?->each(fn(TicketPriceDomainObject $price) => $this->processTicketPrice($ticket, $price))
                ->reject(fn(TicketPriceDomainObject $price) => $this->filterTicketPrice($ticket, $price, $hideSoldOutTickets))
        );
    }

    /**
     * For non-tiered tickets, we can inherit the availability of the ticket.
     *
     * @param TicketPriceDomainObject $price
     * @param TicketDomainObject $ticket
     * @return bool
     */
    private function getPriceAvailability(TicketPriceDomainObject $price, TicketDomainObject $ticket): bool
    {
        if ($ticket->isTieredType()) {
            return !$price->isSoldOut()
                && !$price->isBeforeSaleStartDate()
                && !$price->isAfterSaleEndDate()
                && !$price->getIsHidden();
        }

        return !$ticket->isSoldOut()
            && !$ticket->isBeforeSaleStartDate()
            && !$ticket->isAfterSaleEndDate()
            && !$ticket->getIsHidden();
    }
}
