<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Eloquent\TicketPriceRepository;
use Illuminate\Support\Collection;

readonly class TicketPriceCreateService
{
    public function __construct(
        private TicketPriceRepository $ticketPriceRepository,
    )
    {
    }

    public function createPrices(
        int               $ticketId,
        Collection        $prices,
        EventDomainObject $event,
    ): Collection
    {
        return (new Collection($prices->map(fn(TicketPriceDomainObject $price, int $index) => $this->ticketPriceRepository->create([
            'ticket_id' => $ticketId,
            'price' => $price->getPrice(),
            'label' => $price->getLabel(),
            'sale_start_date' => $price->getSaleStartDate()
                ? DateHelper::convertToUTC($price->getSaleStartDate(), $event->getTimezone())
                : null,
            'sale_end_date' => $price->getSaleEndDate()
                ? DateHelper::convertToUTC($price->getSaleEndDate(), $event->getTimezone())
                : null,
            'initial_quantity_available' => $price->getInitialQuantityAvailable(),
            'is_hidden' => $price->getIsHidden(),
            'order' => $index + 1,
        ]))));
    }
}
