<?php

namespace HiEvents\Services\Application\Handlers\SelfService;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Eloquent\Value\Relationship;
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
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(ResendEmailPublicDTO $dto): void
    {
        $this->loadAndValidateEvent($dto->eventId);
        $order = $this->loadAndValidateOrder($dto->orderShortId, $dto->eventId);

        if ($order->isOrderCancelled()) {
            throw new ResourceConflictException(
                __('Tickets can\'t be resent for a cancelled order.')
            );
        }

        if (! $dto->attendeeShortId) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        $attendee = $this->attendeeRepository
            ->loadRelation(new Relationship(EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->findFirstWhere([
                AttendeeDomainObjectAbstract::SHORT_ID => $dto->attendeeShortId,
                AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
                AttendeeDomainObjectAbstract::EVENT_ID => $dto->eventId,
            ]);

        if (! $attendee) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        if ($attendee->getStatus() === AttendeeStatus::CANCELLED->name) {
            throw new ResourceConflictException(
                __('This ticket has been cancelled and can\'t be resent.')
            );
        }

        $occurrence = $attendee->getEventOccurrence();
        if ($occurrence?->isCancelled()) {
            throw new ResourceConflictException(
                __('The session for this ticket has been cancelled and can\'t be resent.')
            );
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
