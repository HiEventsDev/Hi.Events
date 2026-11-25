<?php

namespace HiEvents\Resources\OrderAuditLog;

use HiEvents\DomainObjects\OrderAuditLogDomainObject;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderAuditLogDomainObject
 */
class TicketHistoryEntryResource extends JsonResource
{
    private const VISIBLE_VALUES = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'event_occurrence_id',
        OrderAuditLogService::CREDENTIALS_REPLACED,
        OrderAuditLogService::RESALE_CLOSED_REASON,
        OrderAuditLogService::RESALE_SOLD_ORDER_ID,
    ];

    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'action' => $this->getAction(),
            'order_id' => $this->getOrderId(),
            'attendee_id' => $this->getAttendeeId(),
            'old_values' => $this->visibleValues($this->getOldValues()),
            'new_values' => $this->visibleValues($this->getNewValues()),
            'created_at' => $this->getCreatedAt(),
        ];
    }

    private function visibleValues(array|string|null $values): object
    {
        $decoded = is_string($values) ? json_decode($values, true) : $values;

        return (object) array_intersect_key($decoded ?? [], array_flip(self::VISIBLE_VALUES));
    }
}
