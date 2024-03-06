<?php

declare(strict_types=1);

namespace HiEvents\Validators;

use HiEvents\DomainObjects\Enums\TicketType;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CreateOrderValidator extends BaseValidator
{
    public function __construct(
        private readonly TicketRepositoryInterface    $ticketRepository,
        private readonly PromoCodeRepositoryInterface $promoCodeRepository,
        private readonly EventRepositoryInterface     $eventRepository
    )
    {
    }

    /**
     * @throws ValidationException
     */
    public function rules(int $eventId, array $data = []): array
    {
        $event = $this->eventRepository->findById($eventId);

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

        $ticketData = collect($data['tickets']);

        if ($ticketData->isEmpty() || $ticketData->sum(fn($ticket) => collect($ticket['quantities'])->sum('quantity')) === 0) {
            throw ValidationException::withMessages([__('You haven\'t selected any tickets')]);
        }

        $ticketIds = $ticketData->pluck('ticket_id');
        $tickets = $this->ticketRepository
            ->loadRelation(TicketPriceDomainObject::class)
            ->findWhereIn('id', $ticketIds->toArray());

        $rules = [];
        foreach ($data['tickets'] as $ticketIndex => $ticketAndQuantities) {
            $validationTicketIdKey = "tickets.$ticketIndex.ticket_id";

            $totalQuantity = collect($ticketAndQuantities['quantities'])->sum('quantity');

            if ($totalQuantity === 0) {
                continue;
            }

            if (!isset($ticketAndQuantities['ticket_id'])) {
                throw ValidationException::withMessages([
                    $validationTicketIdKey => __('Ticket ID must be specified'),
                ]);
            }

            /** @var TicketDomainObject $ticket */
            $ticket = $tickets->filter(fn($t) => $t->getId() === $ticketAndQuantities['ticket_id'])->first();

            $maxPerOrder = (int)$ticket->getMaxPerOrder() ?: 100; // todo - put these in a config
            $minPerOrder = (int)$ticket->getMinPerOrder() ?: 1;

            if ($totalQuantity < $minPerOrder) {
                throw ValidationException::withMessages([
                    $validationTicketIdKey => __('You must order at least :min tickets', ['min' => $minPerOrder]),
                ]);
            }

            if ($totalQuantity > $maxPerOrder) {
                throw ValidationException::withMessages([
                    $validationTicketIdKey => __('You can only order a maximum of :max tickets', ['max' => $maxPerOrder]),
                ]);
            }

            if ($ticket->getEventId() !== $eventId) {
                throw new NotFoundHttpException(
                    sprintf('Ticket ID %d not found', $ticketAndQuantities['ticket_id'])
                );
            }

            if (!isset($ticketAndQuantities['quantities']) || !is_array($ticketAndQuantities['quantities'])) {
                throw ValidationException::withMessages([
                    $validationTicketIdKey => __('Quantities for each ticket must be specified'),
                ]);
            }

            if ($ticket->getType() === TicketType::DONATION->name && (!isset($ticketAndQuantities['quantities'][0]['price']) || $ticketAndQuantities['quantities'][0]['price'] < $ticket->getPrice())) {
                throw ValidationException::withMessages([
                    'tickets.' . $ticketIndex . '.quantities.0.price' => __('The minimum amount is :price', ['price' => Currency::format($ticket->getPrice(), $event->getCurrency())]),
                ]);
            }

            if ($ticket->isSoldOut()) {
                throw ValidationException::withMessages([
                    $validationTicketIdKey => __('This ticket is sold out'),
                ]);
            }

            foreach ($ticketAndQuantities['quantities'] as $quantityIndex => $quantityData) {
                $validationQuantityKey = "tickets.$ticketIndex.quantities.$quantityIndex.quantity";
                $validationPriceIdKey = "tickets.$ticketIndex.quantities.$quantityIndex.price_id";

                if (!isset($quantityData['price_id'])) {
                    throw ValidationException::withMessages([
                        $validationQuantityKey => __('Price ID must be specified'),
                    ]);
                }

                if (!isset($quantityData['quantity'])) {
                    throw ValidationException::withMessages([
                        $validationPriceIdKey => __('Quantity must be specified'),
                    ]);
                }

                $validPriceIds = $ticket->getTicketPrices()?->map(fn(TicketPriceDomainObject $price) => $price->getId());
                $passedPriceIds = (collect($ticketAndQuantities['quantities'])->pluck('price_id'));

                if ($passedPriceIds->diff($validPriceIds)->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        $validationPriceIdKey => __('Invalid price ID'),
                    ]);
                }
            }
        }

        return $rules;
    }
}
