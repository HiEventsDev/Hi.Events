<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\DomainObjects\Enums\TicketType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Eloquent\TicketPriceRepository;
use HiEvents\Services\Domain\Ticket\DTO\TicketPriceDTO;
use HiEvents\Services\Handlers\Ticket\DTO\UpsertTicketDTO;
use Illuminate\Support\Collection;

readonly class TicketPriceCreateService
{
    public function __construct(
        private TicketPriceRepository $ticketPriceRepository,
    )
    {
    }

    public function createPrices(
        TicketDomainObject $ticket,
        UpsertTicketDTO    $ticketsData,
        EventDomainObject  $event,
    ): TicketDomainObject
    {
        if ($ticketsData->type !== TicketType::TIERED) {
            $prices = new Collection([new TicketPriceDTO(
                price: $ticketsData->type === TicketType::FREE ? 0.00 : $ticketsData->price,
                label: null,
                sale_start_date: null,
                sale_end_date: null,
                initial_quantity_available: $ticketsData->initial_quantity_available,
                is_hidden: $ticketsData->is_hidden,
            )]);
        } else {
            $prices = $ticketsData->prices;
        }

        return $ticket->setTicketPrices(new Collection($prices->map(fn(TicketPriceDTO $price, int $index) => $this->ticketPriceRepository->create([
                'ticket_id' => $ticket->getId(),
                'price' => $price->price,
                'label' => $price->label,
                'sale_start_date' => $price->sale_start_date
                    ? DateHelper::convertToUTC($price->sale_start_date, $event->getTimezone())
                    : null,
                'sale_end_date' => $price->sale_end_date
                    ? DateHelper::convertToUTC($price->sale_end_date, $event->getTimezone())
                    : null,
                'initial_quantity_available' => $price->initial_quantity_available,
                'is_hidden' => $price->is_hidden,
                'order' => $index + 1,
            ])))
        );
    }
}
