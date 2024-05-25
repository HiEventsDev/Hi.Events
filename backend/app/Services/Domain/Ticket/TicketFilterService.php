<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Illuminate\Support\Collection;

class TicketFilterService
{
    public function __construct(
        private readonly EventRepositoryInterface    $eventRepository,
        private readonly TaxAndFeeCalculationService $taxCalculationService,
        private readonly TicketPriceService          $ticketPriceService,
    )
    {
    }

    public function filter(EventDomainObject $event, ?PromoCodeDomainObject $promoCode): ?Collection
    {
        $ticketQuantities = $this->eventRepository->getAvailableTicketQuantities($event->getId());

        return $event->getTickets()
            ?->map(fn(TicketDomainObject $ticket) => $this->processTicket($promoCode, $ticket, $ticketQuantities))
            ->reject(fn(TicketDomainObject $ticket) => $this->filterTicket($ticket, $promoCode))
            ->each(fn(TicketDomainObject $ticket) => $this->processTicketPrices($ticket));

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

    private function processTicket(?PromoCodeDomainObject $promoCode, TicketDomainObject $ticket, Collection $ticketQuantities): TicketDomainObject
    {
        if ($this->shouldTicketBeDiscounted($promoCode, $ticket)) {
            $ticket->getTicketPrices()?->each(function (TicketPriceDomainObject $price) use ($ticket, $promoCode) {
                $price->setPriceBeforeDiscount($price->getPrice());
                $price->setPrice($this->ticketPriceService->getIndividualPrice($ticket, $price, $promoCode));
            });
        }

        $ticket->getTicketPrices()?->map(function (TicketPriceDomainObject $price) use ($ticketQuantities) {
            $price->setQuantityAvailable(
                max($ticketQuantities->where('price_id', $price->getId())->first()?->quantity_available, 0)
            );
        });

        return $ticket;
    }

    private function filterTicket(TicketDomainObject $ticket, ?PromoCodeDomainObject $promoCode): bool
    {
        if ($this->isHiddenByPromoCode($ticket, $promoCode)) {
            return true;
        }

        if ($ticket->isSoldOut() && $ticket->getHideWhenSoldOut()) {
            return true;
        }

        if ($ticket->isBeforeSaleStartDate() && $ticket->getHideBeforeSaleStartDate()) {
            return true;
        }

        if ($ticket->isAfterSaleEndDate() && $ticket->getHideAfterSaleEndDate()) {
            return true;
        }

        if ($ticket->getIsHidden()) {
            return true;
        }

        return false;
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

    private function filterTicketPrice(TicketDomainObject $ticket, TicketPriceDomainObject $price): bool
    {
        if (!$ticket->isTieredType()) {
            return false;
        }

        if ($price->isBeforeSaleStartDate() && $ticket->getHideBeforeSaleStartDate()) {
            return true;
        }

        if ($price->isAfterSaleEndDate() && $ticket->getHideAfterSaleEndDate()) {
            return true;
        }

        if ($price->isSoldOut() && $ticket->getHideWhenSoldOut()) {
            return true;
        }

        if ($price->getIsHidden()) {
            return true;
        }

        return false;
    }

    private function processTicketPrices(TicketDomainObject $ticket): void
    {
        $ticket->setTicketPrices(
            $ticket->getTicketPrices()
                ?->each(fn(TicketPriceDomainObject $price) => $this->processTicketPrice($ticket, $price))
                ->reject(fn(TicketPriceDomainObject $price) => $this->filterTicketPrice($ticket, $price))
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
