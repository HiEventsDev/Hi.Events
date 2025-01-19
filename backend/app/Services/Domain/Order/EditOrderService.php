<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;

class EditOrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    )
    {
    }

    public function editOrder(
        int     $id,
        ?string $first_name,
        ?string $last_name,
        ?string $email,
        ?string $notes
    ): OrderDomainObject
    {
        $this->orderRepository->updateWhere(
            attributes: array_filter([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'notes' => $notes,
            ]),
            where: [
                'id' => $id
            ]
        );

        return $this->orderRepository->findById($id);
    }
}
