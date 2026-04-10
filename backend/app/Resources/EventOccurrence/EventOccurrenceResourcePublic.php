<?php

namespace HiEvents\Resources\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin EventOccurrenceDomainObject
 */
class EventOccurrenceResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'short_id' => $this->getShortId(),
            'start_date' => $this->getStartDate(),
            'end_date' => $this->getEndDate(),
            'status' => $this->getStatus(),
            'capacity' => $this->getCapacity(),
            'available_capacity' => $this->getAvailableCapacity(),
            'label' => $this->getLabel(),
            'is_past' => $this->isPast(),
            'is_future' => $this->isFuture(),
            'is_active' => $this->isActive(),
        ];
    }
}
