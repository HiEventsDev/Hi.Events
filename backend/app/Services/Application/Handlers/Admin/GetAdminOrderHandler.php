<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;

class GetAdminOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    )
    {
    }

    public function handle(int $orderId): ?OrderDomainObject
    {
        return $this->orderRepository->getOrderByIdForAdmin($orderId);
    }
}
