<?php

namespace TicketKitten\Service\Common\Ticket;

use Illuminate\Support\Collection;
use TicketKitten\DomainObjects\Enums\TicketType;
use TicketKitten\DomainObjects\TicketDomainObject;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Exceptions\CannotDeleteEntityException;
use TicketKitten\Http\DataTransferObjects\TicketPriceDTO;
use TicketKitten\Http\DataTransferObjects\UpsertTicketDTO;
use TicketKitten\Repository\Eloquent\TicketPriceRepository;

readonly class TicketPriceUpdateService
{
    public function __construct(
        private TicketPriceRepository $ticketPriceRepository,
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
        Collection         $existingPrices
    ): void
    {
        if ($ticketsData->type !== TicketType::TIERED) {
            $prices = new Collection([new TicketPriceDTO(
                price: $ticketsData->type === TicketType::FREE ? 0.00 : $ticketsData->price,
                label: null,
                sale_start_date: null,
                sale_end_date: null,
                initial_quantity_available: $ticketsData->initial_quantity_available,
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
                    'sale_start_date' => $price->sale_start_date,
                    'sale_end_date' => $price->sale_end_date,
                    'initial_quantity_available' => $price->initial_quantity_available,
                    'is_hidden' => $price->is_hidden,
                    'order' => $order++,
                ]);
            } else {
                $this->ticketPriceRepository->updateWhere([
                    'ticket_id' => $ticket->getId(),
                    'price' => $price->price,
                    'label' => $price->label,
                    'sale_start_date' => $price->sale_start_date,
                    'sale_end_date' => $price->sale_end_date,
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

    private function deletePrices(?Collection $prices, Collection $existingPrices): void
    {
        $pricesIds = $prices->map(fn($price) => $price->id)->toArray();

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
