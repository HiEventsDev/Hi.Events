<?php

namespace HiEvents\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\OrderAuditLogRepositoryInterface;

class OrderAuditLogService
{
    public function __construct(
        private readonly OrderAuditLogRepositoryInterface $orderAuditLogRepository,
    ) {}

    public function logAttendeeUpdate(
        AttendeeDomainObject $attendee,
        array $oldValues,
        array $newValues,
        string $ipAddress,
        ?string $userAgent
    ): void {
        $changedFields = array_keys($newValues);

        $this->orderAuditLogRepository->create([
            'event_id' => $attendee->getEventId(),
            'order_id' => $attendee->getOrderId(),
            'attendee_id' => $attendee->getId(),
            'action' => OrderAuditAction::ATTENDEE_UPDATED->value,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => implode(',', $changedFields),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function logOrderUpdate(
        OrderDomainObject $order,
        array $oldValues,
        array $newValues,
        string $ipAddress,
        ?string $userAgent
    ): void {
        $changedFields = array_keys($newValues);

        $this->orderAuditLogRepository->create([
            'event_id' => $order->getEventId(),
            'order_id' => $order->getId(),
            'attendee_id' => null,
            'action' => OrderAuditAction::ORDER_UPDATED->value,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => implode(',', $changedFields),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function logEmailResent(
        string $action,
        int $eventId,
        int $orderId,
        ?int $attendeeId,
        string $ipAddress,
        ?string $userAgent
    ): void {
        $this->orderAuditLogRepository->create([
            'event_id' => $eventId,
            'order_id' => $orderId,
            'attendee_id' => $attendeeId,
            'action' => $action,
            'old_values' => null,
            'new_values' => null,
            'changed_fields' => null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
