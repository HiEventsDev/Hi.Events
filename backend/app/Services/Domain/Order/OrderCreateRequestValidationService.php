<?php

namespace HiEvents\Services\Domain\Order;

use Exception;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\TicketType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Services\Domain\Ticket\AvailableTicketQuantitiesFetchService;
use HiEvents\Services\Domain\Ticket\DTO\AvailableTicketQuantitiesDTO;
use HiEvents\Services\Domain\Ticket\DTO\AvailableTicketQuantitiesResponseDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderCreateRequestValidationService
{
    private AvailableTicketQuantitiesResponseDTO $availableTicketQuantities;

    public function __construct(
        readonly private TicketRepositoryInterface             $ticketRepository,
        readonly private PromoCodeRepositoryInterface          $promoCodeRepository,
        readonly private EventRepositoryInterface              $eventRepository,
        readonly private AvailableTicketQuantitiesFetchService $fetchAvailableTicketQuantitiesService,
    )
    {
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function validateRequestData(int $eventId, array $data = []): void
    {
        $this->validateTypes($data);

        $event = $this->eventRepository->findById($eventId);
        $this->validatePromoCode($eventId, $data);
        $this->validateTicketSelection($data);

        $this->availableTicketQuantities = $this->fetchAvailableTicketQuantitiesService
            ->getAvailableTicketQuantities(
                $event->getId(),
                ignoreCache: true,
            );

        $this->validateOverallCapacity($data);
        $this->validateTicketDetails($event, $data);
    }

    /**
     * @throws ValidationException
     */
    private function validatePromoCode(int $eventId, array $data): void
    {
        if (isset($data['promo_code'])) {
            $promoCode = $this->promoCodeRepository->findFirstWhere([
                PromoCodeDomainObjectAbstract::CODE => strtolower(trim($data['promo_code'])),
                PromoCodeDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

            if (!$promoCode) {
                throw ValidationException::withMessages([
                    'promo_code' => __('This promo code is invalid'),
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateTypes(array $data): void
    {
        $validator = Validator::make($data, [
            'tickets' => 'required|array',
            'tickets.*.ticket_id' => 'required|integer',
            'tickets.*.quantities' => 'required|array',
            'tickets.*.quantities.*.quantity' => 'required|integer',
            'tickets.*.quantities.*.price_id' => 'required|integer',
            'tickets.*.quantities.*.price' => 'numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateTicketSelection(array $data): void
    {
        $ticketData = collect($data['tickets']);
        if ($ticketData->isEmpty() || $ticketData->sum(fn($ticket) => collect($ticket['quantities'])->sum('quantity')) === 0) {
            throw ValidationException::withMessages([
                'tickets' => __('You haven\'t selected any tickets')
            ]);
        }
    }

    /**
     * @throws Exception
     */
    private function getTickets(array $data): Collection
    {
        $ticketIds = collect($data['tickets'])->pluck('ticket_id');
        return $this->ticketRepository
            ->loadRelation(TicketPriceDomainObject::class)
            ->findWhereIn('id', $ticketIds->toArray());
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    private function validateTicketDetails(EventDomainObject $event, array $data): void
    {
        $tickets = $this->getTickets($data);

        foreach ($data['tickets'] as $ticketIndex => $ticketAndQuantities) {
            $this->validateSingleTicketDetails($event, $ticketIndex, $ticketAndQuantities, $tickets);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateSingleTicketDetails(EventDomainObject $event, int $ticketIndex, array $ticketAndQuantities, $tickets): void
    {
        $ticketId = $ticketAndQuantities['ticket_id'];
        $totalQuantity = collect($ticketAndQuantities['quantities'])->sum('quantity');

        if ($totalQuantity === 0) {
            return;
        }

        /** @var TicketDomainObject $ticket */
        $ticket = $tickets->filter(fn($t) => $t->getId() === $ticketId)->first();
        if (!$ticket) {
            throw new NotFoundHttpException(sprintf('Ticket ID %d not found', $ticketId));
        }

        $this->validateTicketEvent(
            event: $event,
            ticketId: $ticketId,
            ticket: $ticket
        );

        $this->validateTicketQuantity(
            ticketIndex: $ticketIndex,
            ticketAndQuantities: $ticketAndQuantities,
            ticket: $ticket
        );

        $this->validateTicketTypeAndPrice(
            event: $event,
            ticketIndex: $ticketIndex,
            ticketAndQuantities: $ticketAndQuantities,
            ticket: $ticket
        );

        $this->validateSoldOutTickets(
            ticketId: $ticketId,
            ticketIndex: $ticketIndex,
            ticket: $ticket
        );

        $this->validatePriceIdAndQuantity(
            ticketIndex: $ticketIndex,
            ticketAndQuantities: $ticketAndQuantities,
            ticket: $ticket
        );
    }

    /**
     * @throws ValidationException
     */
    private function validateTicketQuantity(int $ticketIndex, array $ticketAndQuantities, TicketDomainObject $ticket): void
    {
        $totalQuantity = collect($ticketAndQuantities['quantities'])->sum('quantity');
        $maxPerOrder = (int)$ticket->getMaxPerOrder() ?: 100;

        $capacityMaximum = $this->availableTicketQuantities
            ->ticketQuantities
            ->where('ticket_id', $ticket->getId())
            ->map(fn(AvailableTicketQuantitiesDTO $price) => $price->capacities)
            ->flatten()
            ->min(fn(CapacityAssignmentDomainObject $capacity) => $capacity->getCapacity());

        $ticketAvailableQuantity = $this->availableTicketQuantities
            ->ticketQuantities
            ->first(fn(AvailableTicketQuantitiesDTO $price) => $price->ticket_id === $ticket->getId())
            ->quantity_available;

        # if there are fewer tickets available than the configured minimum, we allow less than the minimum to be purchased
        $minPerOrder = min((int)$ticket->getMinPerOrder() ?: 1,
            $capacityMaximum ?: $maxPerOrder,
            $ticketAvailableQuantity ?: $maxPerOrder);

        $this->validateTicketPricesQuantity(
            quantities: $ticketAndQuantities['quantities'],
            ticket: $ticket,
            ticketIndex: $ticketIndex
        );

        if ($totalQuantity > $maxPerOrder) {
            throw ValidationException::withMessages([
                "tickets.$ticketIndex" => __("The maximum number of tickets available for :tickets is :max", [
                    'max' => $maxPerOrder,
                    'ticket' => $ticket->getTitle(),
                ]),
            ]);
        }

        if ($totalQuantity < $minPerOrder) {
            throw ValidationException::withMessages([
                "tickets.$ticketIndex" => __("You must order at least :min tickets for :ticket", [
                    'min' => $minPerOrder,
                    'ticket' => $ticket->getTitle(),
                ]),
            ]);
        }
    }

    private function validateTicketEvent(EventDomainObject $event, int $ticketId, TicketDomainObject $ticket): void
    {
        if ($ticket->getEventId() !== $event->getId()) {
            throw new NotFoundHttpException(sprintf('Ticket ID %d not found for event ID %d', $ticketId, $event->getId()));
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateTicketTypeAndPrice(
        EventDomainObject  $event,
        int                $ticketIndex,
        array              $ticketAndQuantities,
        TicketDomainObject $ticket
    ): void
    {
        if ($ticket->getType() === TicketType::DONATION->name) {
            $price = $ticketAndQuantities['quantities'][0]['price'] ?? 0;
            if ($price < $ticket->getPrice()) {
                $formattedPrice = Currency::format($ticket->getPrice(), $event->getCurrency());
                throw ValidationException::withMessages([
                    "tickets.$ticketIndex.quantities.0.price" => __("The minimum amount is :price", ['price' => $formattedPrice]),
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateSoldOutTickets(int $ticketId, int $ticketIndex, TicketDomainObject $ticket): void
    {
        if ($ticket->isSoldOut()) {
            throw ValidationException::withMessages([
                "tickets.$ticketIndex" => __("The ticket :ticket is sold out", [
                    'id' => $ticketId,
                    'ticket' => $ticket->getTitle(),
                ]),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validatePriceIdAndQuantity(int $ticketIndex, array $ticketAndQuantities, TicketDomainObject $ticket): void
    {
        $errors = [];

        foreach ($ticketAndQuantities['quantities'] as $quantityIndex => $quantityData) {
            $priceId = $quantityData['price_id'] ?? null;
            $quantity = $quantityData['quantity'] ?? null;

            if (null === $priceId || null === $quantity) {
                $missingField = null === $priceId ? 'price_id' : 'quantity';
                $errors["tickets.$ticketIndex.quantities.$quantityIndex.$missingField"] = __(":field must be specified", [
                    'field' => ucfirst($missingField)
                ]);
            }

            $validPriceIds = $ticket->getTicketPrices()?->map(fn(TicketPriceDomainObject $price) => $price->getId());
            if (!in_array($priceId, $validPriceIds->toArray(), true)) {
                $errors["tickets.$ticketIndex.quantities.$quantityIndex.price_id"] = __('Invalid price ID');
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateTicketPricesQuantity(array $quantities, TicketDomainObject $ticket, int $ticketIndex): void
    {
        foreach ($quantities as $ticketQuantity) {
            $numberAvailable = $this->availableTicketQuantities
                ->ticketQuantities
                ->where('ticket_id', $ticket->getId())
                ->where('price_id', $ticketQuantity['price_id'])
                ->first()?->quantity_available;

            /** @var TicketPriceDomainObject $ticketPrice */
            $ticketPrice = $ticket->getTicketPrices()
                ?->first(fn(TicketPriceDomainObject $price) => $price->getId() === $ticketQuantity['price_id']);

            if ($ticketQuantity['quantity'] > $numberAvailable) {
                if ($numberAvailable === 0) {
                    throw ValidationException::withMessages([
                        "tickets.$ticketIndex" => __("The ticket :ticket is sold out", [
                            'ticket' => $ticket->getTitle() . ($ticketPrice->getLabel() ? ' - ' . $ticketPrice->getLabel() : ''),
                        ]),
                    ]);
                }

                throw ValidationException::withMessages([
                    "tickets.$ticketIndex" => __("The maximum number of tickets available for :ticket is :max", [
                        'max' => $numberAvailable,
                        'ticket' => $ticket->getTitle() . ($ticketPrice->getLabel() ? ' - ' . $ticketPrice->getLabel() : ''),
                    ]),
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateOverallCapacity(array $data): void
    {
        foreach ($this->availableTicketQuantities->capacities as $capacity) {
            if ($capacity->getTickets() === null) {
                continue;
            }

            $ticketIds = $capacity->getTickets()->map(fn(TicketDomainObject $ticket) => $ticket->getId());
            $totalQuantity = collect($data['tickets'])
                ->filter(fn($ticket) => in_array($ticket['ticket_id'], $ticketIds->toArray(), true))
                ->sum(fn($ticket) => collect($ticket['quantities'])->sum('quantity'));

            $reservedTicketQuantities = $capacity->getTickets()
                ->map(fn(TicketDomainObject $ticket) => $this
                    ->availableTicketQuantities
                    ->ticketQuantities
                    ->where('ticket_id', $ticket->getId())
                    ->sum('quantity_reserved')
                )
                ->sum();

            if ($totalQuantity > ($capacity->getAvailableCapacity() - $reservedTicketQuantities)) {
                if ($capacity->getAvailableCapacity() - $reservedTicketQuantities <= 0) {
                    throw ValidationException::withMessages([
                        'tickets' => __('Sorry, these tickets are sold out'),
                    ]);
                }

                throw ValidationException::withMessages([
                    'tickets' => __('The maximum number of tickets available is :max', [
                        'max' => $capacity->getAvailableCapacity(),
                    ]),
                ]);
            }
        }
    }
}
