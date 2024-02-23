<?php

namespace TicketKitten\Http\Actions\Orders;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\AttendeeDomainObject;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\DomainObjects\QuestionAndAnswerViewDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Resources\Order\OrderResource;

class GetOrderAction extends BaseAction
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function __invoke(int $eventId, int $orderId): JsonResponse
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(AttendeeDomainObject::class)
            ->loadRelation(QuestionAndAnswerViewDomainObject::class)
            ->findById($orderId);

        return $this->resourceResponse(OrderResource::class, $order);
    }
}
