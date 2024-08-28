<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\QuestionAndAnswerViewDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Resources\Order\OrderResource;
use Illuminate\Http\JsonResponse;

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
