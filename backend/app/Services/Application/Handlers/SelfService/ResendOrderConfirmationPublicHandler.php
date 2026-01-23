<?php

namespace HiEvents\Services\Application\Handlers\SelfService;

use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\SelfService\DTO\ResendEmailPublicDTO;
use HiEvents\Services\Domain\SelfService\SelfServiceResendEmailService;

class ResendOrderConfirmationPublicHandler
{
    use SelfServiceValidationTrait;

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly SelfServiceResendEmailService $selfServiceResendEmailService,
    ) {
    }

    public function handle(ResendEmailPublicDTO $dto): void
    {
        $this->loadAndValidateEvent($dto->eventId);
        $order = $this->loadAndValidateOrder($dto->orderShortId, $dto->eventId);

        $this->selfServiceResendEmailService->resendOrderConfirmation(
            orderId: $order->getId(),
            eventId: $dto->eventId,
            ipAddress: $dto->ipAddress,
            userAgent: $dto->userAgent
        );
    }
}
