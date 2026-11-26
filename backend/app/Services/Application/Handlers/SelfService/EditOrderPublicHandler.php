<?php

namespace HiEvents\Services\Application\Handlers\SelfService;

use HiEvents\Exceptions\SelfServiceDisabledException;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\SelfService\DTO\EditOrderPublicDTO;
use HiEvents\Services\Domain\SelfService\DTO\EditOrderResultDTO;
use HiEvents\Services\Domain\SelfService\SelfServiceEditOrderService;
use HiEvents\Services\Domain\SelfService\VerifiedTicketAccessService;

class EditOrderPublicHandler
{
    use SelfServiceValidationTrait;

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly SelfServiceEditOrderService $selfServiceEditOrderService,
        private readonly VerifiedTicketAccessService $verifiedTicketAccessService,
    ) {}

    /**
     * @throws SelfServiceDisabledException
     * @throws SelfServiceVerificationRequiredException
     */
    public function handle(EditOrderPublicDTO $dto): EditOrderResultDTO
    {
        $this->loadAndValidateEvent($dto->eventId);
        $order = $this->loadAndValidateOrder($dto->orderShortId, $dto->eventId);

        if ($dto->email !== null && $dto->email !== $order->getEmail()) {
            $this->verifiedTicketAccessService->assertControlsOrder($dto->sessionToken, $order);
        }

        return $this->selfServiceEditOrderService->editOrder(
            order: $order,
            firstName: $dto->firstName,
            lastName: $dto->lastName,
            email: $dto->email,
            ipAddress: $dto->ipAddress,
            userAgent: $dto->userAgent,
            phone: $dto->phone,
        );
    }
}
