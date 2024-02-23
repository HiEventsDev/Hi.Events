<?php

declare(strict_types=1);

namespace TicketKitten\Validator;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use TicketKitten\DomainObjects\Enums\TicketType;
use TicketKitten\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use TicketKitten\DomainObjects\TicketDomainObject;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Helper\Currency;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\PromoCodeRepositoryInterface;
use TicketKitten\Repository\Interfaces\TicketRepositoryInterface;

/**
 * todo -  validate quantity, return better messages
 */
class CreateOrderValidator extends BaseValidator
{
    public function __construct(
        private readonly TicketRepositoryInterface    $ticketRepository,
        private readonly PromoCodeRepositoryInterface $promoCodeRepository,
        private readonly EventRepositoryInterface $eventRepository
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
                $rules[$validationTicketIdKey] = 'required';
                continue;
            }

            /** @var TicketDomainObject $ticket */
            $ticket = $tickets->filter(fn($t) => $t->getId() === $ticketAndQuantities['ticket_id'])->first();

            if (!$ticket || $ticket->getEventId() !== $eventId) {
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
                    'tickets.' . $ticketIndex . '.quantities.0.price' => __('The minimum donation amount is :price', ['price' => Currency::format($ticket->getPrice(), $event->getCurrency())]),
                ]);
            }

            if ($ticket->isSoldOut()) {
                throw ValidationException::withMessages([
                    $validationTicketIdKey => __('This ticket is sold out'),
                ]);
            }

            // todo validate quantity
//            $maxPerOrder = $this->getMaxPerOrder($ticket);
//            $minPerOrder = (int)$ticket->getMinPerOrder() ?: self::DEFAULT_MIN_PER_TRANSACTION;
//
//            $validationRules[] = "required|integer";
//            $validationRules[] = "min:$minPerOrder";
//
//            if ($maxPerOrder !== Constants::INFINITE) {
//                $validationRules[] = "max:$maxPerOrder";
//            }
//
//            $rules[$validationQuantityKey] = implode(self::RULE_DELIMITER, $validationRules);

            foreach ($ticketAndQuantities['quantities'] as $quantityIndex => $quantityData) {
                $validationQuantityKey = "tickets.$ticketIndex.quantities.$quantityIndex.quantity";
                $validationPriceIdKey = "tickets.$ticketIndex.quantities.$quantityIndex.price_id";

                if (!isset($quantityData['price_id'], $quantityData['quantity'])) {
                    $rules[$validationQuantityKey] = 'required';
                    $rules[$validationPriceIdKey] = 'required';
                    continue;
                }

                $validPriceIds = $ticket->getTicketPrices()?->map(fn(TicketPriceDomainObject $price) => $price->getId());
                $rules[$validationPriceIdKey] = "required|in:" . implode(',', $validPriceIds->toArray());

                $validationRules = ["required|integer|min:0"];

                $rules[$validationQuantityKey] = implode('|', $validationRules);
            }
        }

        return $rules;
    }

    private function getMaxPerOrder(TicketDomainObject $ticket): int
    {
        $quantityRemaining = $this->ticketRepository->getQuantityRemaining($ticket->getId());
        $maxPerOrder = $ticket->getMaxPerOrder();

        return $maxPerOrder ? min($maxPerOrder, $quantityRemaining) : $quantityRemaining;
    }
}
