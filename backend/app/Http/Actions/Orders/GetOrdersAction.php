<?php

namespace TicketKitten\Http\Actions\Orders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TicketKitten\DomainObjects\AttendeeDomainObject;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\QueryParamsDTO;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Resources\Order\OrderResource;

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
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(AttendeeDomainObject::class)
            ->findByEventId($eventId, QueryParamsDTO::fromArray($request->query->all()));

        return $this->filterableResourceResponse(
            resource: OrderResource::class,
            data: $orders,
            domainObject: OrderDomainObject::class
        );
    }
}
