<?php

namespace HiEvents\Services\Domain\SelfService;

use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\SelfService\DTO\SelfServiceSubjectDTO;

class SelfServiceSubjectResolver
{
    private const MANAGEABLE_ORDER_STATUSES = [
        OrderStatus::COMPLETED->name,
        OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
    ];

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function forOrder(int $eventId, string $orderShortId): SelfServiceSubjectDTO
    {
        $order = $this->orderRepository->findFirstWhere([
            OrderDomainObjectAbstract::SHORT_ID => $orderShortId,
            OrderDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        $isManageable = $order !== null
            && $order->getEmail() !== null
            && in_array($order->getStatus(), self::MANAGEABLE_ORDER_STATUSES, true);

        if (! $isManageable) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        return new SelfServiceSubjectDTO(email: $order->getEmail(), locale: $order->getLocale());
    }

    /**
     * @throws ResourceNotFoundException
     * @throws SelfServiceVerificationException
     */
    public function forAttendee(int $eventId, string $attendeeShortId): SelfServiceSubjectDTO
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            AttendeeDomainObjectAbstract::SHORT_ID => $attendeeShortId,
            AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($attendee === null) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        if ($attendee->getEmail() === null) {
            throw new SelfServiceVerificationException(__('This ticket has no email address on file, so it cannot be managed online.'));
        }

        return new SelfServiceSubjectDTO(email: $attendee->getEmail(), locale: $attendee->getLocale());
    }
}
