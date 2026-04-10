<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Resources\Order\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOrdersAction extends BaseAction
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $orders = $this->orderRepository
            ->loadRelation(new Relationship(
                domainObject: OrderItemDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: EventOccurrenceDomainObject::class,
                        name: 'event_occurrence',
                    ),
                ],
            ))
            ->loadRelation(AttendeeDomainObject::class)
            ->loadRelation(InvoiceDomainObject::class)
            ->findByEventId($eventId, $this->getPaginationQueryParams($request));

        return $this->filterableResourceResponse(
            resource: OrderResource::class,
            data: $orders,
            domainObject: OrderDomainObject::class
        );
    }
}
