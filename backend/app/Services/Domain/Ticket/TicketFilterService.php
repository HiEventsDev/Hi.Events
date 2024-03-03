<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Illuminate\Support\Collection;

readonly class TicketFilterService
{
    public function __construct(
        private EventRepositoryInterface    $eventRepository,
        private TaxAndFeeCalculationService $taxCalculationService,
        private TicketPriceService          $ticketPriceService,
    )
    {
    }

    public function filter(EventDomainObject $event, ?PromoCodeDomainObject $promoCode): ?Collection
    {
        $ticketQuantities = $this->eventRepository->getAvailableTicketQuantities($event->getId());

        return $event->getTickets()
            ?->map(function (TicketDomainObject $ticket) use ($promoCode, $ticketQuantities) {
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
            })->reject(function (TicketDomainObject $ticket) use ($promoCode) {
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
            })->each(function (TicketDomainObject $ticket) use ($promoCode) {
                $ticket->setTicketPrices($ticket->getTicketPrices()?->each(function (TicketPriceDomainObject $price) use ($ticket, $promoCode) {
                    $taxAndFees = $this->taxCalculationService
                        ->calculateTaxAndFeesForTicketPrice($ticket, $price);

                    $price
                        ->setTaxTotal(Currency::round($taxAndFees->taxTotal))
                        ->setFeeTotal(Currency::round($taxAndFees->feeTotal));
                })->reject(function (TicketPriceDomainObject $price) {
                    if ($price->isBeforeSaleStartDate()) {
                        return true;
                    }

                    if ($price->isAfterSaleEndDate()) {
                        return true;
                    }

                    if ($price->isSoldOut()) {
                        return true;
                    }

                    if ($price->getIsHidden()) {
                        return true;
                    }

                    return false;
                }));
            });
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
}
