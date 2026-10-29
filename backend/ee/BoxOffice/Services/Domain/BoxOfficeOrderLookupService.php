<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;

class BoxOfficeOrderLookupService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function findOrFail(BoxOfficeDomainObject $boxOffice, string $orderShortId): OrderDomainObject
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(OrderItemDomainObject::class))
            ->loadRelation(new Relationship(AttendeeDomainObject::class))
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->findFirstWhere([
                'short_id' => $orderShortId,
                'box_office_id' => $boxOffice->getId(),
                'event_id' => $boxOffice->getEventId(),
            ]);

        if ($order === null) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        return $order;
    }
}
