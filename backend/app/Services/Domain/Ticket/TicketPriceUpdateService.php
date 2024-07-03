<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\DomainObjects\Enums\TicketType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Eloquent\TicketPriceRepository;
use HiEvents\Services\Domain\Ticket\DTO\TicketPriceDTO;
use HiEvents\Services\Handlers\Ticket\DTO\UpsertTicketDTO;
use Illuminate\Support\Collection;

class TicketPriceUpdateService
{
    public function __construct(
        private readonly TicketPriceRepository $ticketPriceRepository,
    )
    {
    }

    /**
     * @throws CannotDeleteEntityException
     */
    public function updatePrices(
        TicketDomainObject $ticket,
        UpsertTicketDTO    $ticketsData,
        /** @var Collection<TicketPriceDomainObject> $existingPrices */
        Collection         $existingPrices,
        EventDomainObject  $event,
    ): void
    {
        if ($ticketsData->type !== TicketType::TIERED) {
            $prices = new Collection([new TicketPriceDTO(
                price: $ticketsData->type === TicketType::FREE ? 0.00 : $ticketsData->prices->first()->price,
                label: null,
                sale_start_date: null,
                sale_end_date: null,
                initial_quantity_available: $ticketsData->prices->first()->initial_quantity_available,
                id: $existingPrices->first()->getId(),
            )]);
        } else {
            $prices = $ticketsData->prices;
        }

        $order = 1;

        foreach ($prices as $price) {
            if ($price->id === null) {
                $this->ticketPriceRepository->create([
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
                    'order' => $order++,
                ]);
            } else {
                $this->ticketPriceRepository->updateWhere([
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
                    'order' => $order++,
                ], [
                    'id' => $price->id,
                ]);
            }
        }

        $this->deletePrices($prices, $existingPrices);
    }

    /**
     * @throws CannotDeleteEntityException
     */
    private function deletePrices(?Collection $prices, Collection $existingPrices): void
    {
        $pricesIds = $prices?->map(fn($price) => $price->id)->toArray();

        $existingPrices->each(function (TicketPriceDomainObject $price) use ($pricesIds) {
            if (in_array($price->getId(), $pricesIds)) {
                return;
            }
            if ($price->getQuantitySold() > 0) {
                throw new CannotDeleteEntityException(
                    __('Cannot delete ticket price with id :id because it has sales', ['id' => $price->getId()])
                );
            }
            $this->ticketPriceRepository->deleteById($price->getId());
        });
    }
}
