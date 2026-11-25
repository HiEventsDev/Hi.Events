<?php

namespace HiEvents\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Repository\Interfaces\OrderAuditLogRepositoryInterface;

class OrderAuditLogService
{
    public const CREDENTIALS_REPLACED = 'credentials_replaced';

    public const RESALE_CLOSED_REASON = 'resale_closed_reason';

    public const RESALE_SOLD_ORDER_ID = 'resale_sold_order_id';

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

    public function logOrganizerAttendeeUpdate(
        AttendeeDomainObject $attendee,
        array $oldValues,
        array $newValues,
    ): void {
        $this->orderAuditLogRepository->create([
            'event_id' => $attendee->getEventId(),
            'order_id' => $attendee->getOrderId(),
            'attendee_id' => $attendee->getId(),
            'action' => OrderAuditAction::ATTENDEE_UPDATED_BY_ORGANIZER->value,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => implode(',', array_keys($newValues)),
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }

    public function logTicketTransfer(
        OrderAuditAction $action,
        AttendeeDomainObject $attendee,
        string $senderEmail,
        string $recipientEmail,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->orderAuditLogRepository->create([
            'event_id' => $attendee->getEventId(),
            'order_id' => $attendee->getOrderId(),
            'attendee_id' => $attendee->getId(),
            'action' => $action->value,
            'old_values' => ['email' => $senderEmail],
            'new_values' => $action === OrderAuditAction::TICKET_TRANSFER_ACCEPTED
                ? ['email' => $recipientEmail, self::CREDENTIALS_REPLACED => true]
                : ['email' => $recipientEmail],
            'changed_fields' => 'email',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function logTicketResale(
        OrderAuditAction $action,
        TicketResaleListingDomainObject $listing,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->orderAuditLogRepository->create([
            'event_id' => $listing->getEventId(),
            'order_id' => $listing->getOrderId(),
            'attendee_id' => $listing->getAttendeeId(),
            'action' => $action->value,
            'old_values' => [],
            'new_values' => array_filter([
                self::RESALE_CLOSED_REASON => $listing->getClosedReason(),
                self::RESALE_SOLD_ORDER_ID => $listing->getSoldOrderId(),
            ], static fn ($value) => $value !== null),
            'changed_fields' => '',
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

    public function logManualAttendeeCapacityOverride(
        int $eventId,
        int $orderId,
        int $attendeeId,
        int $occurrenceId,
        string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->orderAuditLogRepository->create([
            'event_id' => $eventId,
            'order_id' => $orderId,
            'attendee_id' => $attendeeId,
            'action' => OrderAuditAction::MANUAL_ATTENDEE_CAPACITY_OVERRIDE->value,
            'old_values' => null,
            'new_values' => ['event_occurrence_id' => $occurrenceId],
            'changed_fields' => 'event_occurrence_id',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function logBoxOfficeAction(
        OrderAuditAction $action,
        int $eventId,
        int $orderId,
        string $operatorName,
        array $details,
        string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->orderAuditLogRepository->create([
            'event_id' => $eventId,
            'order_id' => $orderId,
            'attendee_id' => null,
            'action' => $action->value,
            'old_values' => null,
            'new_values' => array_merge(['operator_name' => $operatorName], $details),
            'changed_fields' => implode(',', array_keys($details)),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
