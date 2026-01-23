<?php

namespace HiEvents\Services\Application\Handlers\SelfService;

use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\SelfService\DTO\ResendEmailPublicDTO;
use HiEvents\Services\Domain\SelfService\SelfServiceResendEmailService;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class ResendAttendeeTicketPublicHandler
{
    use SelfServiceValidationTrait;

    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly SelfServiceResendEmailService $selfServiceResendEmailService,
    ) {
    }

    public function handle(ResendEmailPublicDTO $dto): void
    {
        $this->loadAndValidateEvent($dto->eventId);
        $order = $this->loadAndValidateOrder($dto->orderShortId, $dto->eventId);

        if (!$dto->attendeeShortId) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        $attendee = $this->attendeeRepository->findFirstWhere([
            AttendeeDomainObjectAbstract::SHORT_ID => $dto->attendeeShortId,
            AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
            AttendeeDomainObjectAbstract::EVENT_ID => $dto->eventId,
        ]);

        if (!$attendee) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        $this->selfServiceResendEmailService->resendAttendeeTicket(
            attendeeId: $attendee->getId(),
            orderId: $order->getId(),
            eventId: $dto->eventId,
            ipAddress: $dto->ipAddress,
            userAgent: $dto->userAgent
        );
    }
}
