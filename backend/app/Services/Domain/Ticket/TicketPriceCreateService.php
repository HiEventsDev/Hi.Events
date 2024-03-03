<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\DomainObjects\Enums\TicketType;
use HiEvents\DomainObjects\TicketDomainObject;
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
        bool               $removeExisting = false
    ): TicketDomainObject
    {
        if ($removeExisting) {
            $this->ticketPriceRepository->deleteWhere(['ticket_id' => $ticket->getId()]);
        }

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

        return $ticket->setTicketPrices(new Collection($prices->map(function (TicketPriceDTO $price, int $index) use ($ticket) {
                return $this->ticketPriceRepository->create([
                    'ticket_id' => $ticket->getId(),
                    'price' => $price->price,
                    'label' => $price->label,
                    'sale_start_date' => $price->sale_start_date,
                    'sale_end_date' => $price->sale_end_date,
                    'initial_quantity_available' => $price->initial_quantity_available,
                    'is_hidden' => $price->is_hidden,
                    'order' => $index + 1,
                ]);
            }))
        );
    }
}
