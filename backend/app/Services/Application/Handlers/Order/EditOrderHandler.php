<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Services\Application\Handlers\Order\DTO\EditOrderDTO;
use HiEvents\Services\Domain\Order\EditOrderService;
use Psr\Log\LoggerInterface;
use Throwable;

class EditOrderHandler
{
    public function __construct(
        private readonly EditOrderService $editOrderService,
        private readonly LoggerInterface  $logger,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(EditOrderDTO $dto): OrderDomainObject
    {
        $this->logger->info(__('Editing order with ID: :id', [
            'id' => $dto->id,
        ]));

        return $this->editOrderService->editOrder(
            id: $dto->id,
            eventId: $dto->eventId,
            firstName: $dto->firstName,
            lastName: $dto->lastName,
            email: $dto->email,
            notes: $dto->notes
        );
    }
}
