<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Database\DatabaseManager;
use Throwable;

class EditOrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface     $orderRepository,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly DatabaseManager              $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function editOrder(
        int     $id,
        ?string $first_name,
        ?string $last_name,
        ?string $email,
        ?string $notes
    ): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($id, $first_name, $last_name, $email, $notes) {
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

            $this->domainEventDispatcherService->dispatch(
                new OrderEvent(
                    type: DomainEventType::ORDER_UPDATED,
                    orderId: $id,
                ),
            );

            return $this->orderRepository->findById($id);
        });
    }
}
