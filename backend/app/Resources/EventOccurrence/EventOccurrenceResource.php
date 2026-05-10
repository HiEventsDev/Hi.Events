<?php

namespace HiEvents\Resources\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin EventOccurrenceDomainObject
 */
class EventOccurrenceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $stats = $this->getEventOccurrenceStatistics();

        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'short_id' => $this->getShortId(),
            'start_date' => $this->getStartDate(),
            'end_date' => $this->getEndDate(),
            'status' => $this->getStatus(),
            'capacity' => $this->getCapacity(),
            'used_capacity' => $this->getUsedCapacity(),
            'available_capacity' => $this->getAvailableCapacity(),
            'label' => $this->getLabel(),
            'is_overridden' => $this->getIsOverridden(),
            'is_past' => $this->isPast(),
            'is_future' => $this->isFuture(),
            'is_active' => $this->isActive(),
            'statistics' => $this->when($stats !== null, fn() => [
                'total_gross_sales' => $stats->getSalesTotalGross() ?? 0,
                'total_tax' => $stats->getTotalTax() ?? 0,
                'total_fee' => $stats->getTotalFee() ?? 0,
                'orders_created' => $stats->getOrdersCreated() ?? 0,
                'total_refunded' => $stats->getTotalRefunded() ?? 0,
                'attendees_registered' => $stats->getAttendeesRegistered() ?? 0,
                'products_sold' => $stats->getProductsSold() ?? 0,
            ]),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
