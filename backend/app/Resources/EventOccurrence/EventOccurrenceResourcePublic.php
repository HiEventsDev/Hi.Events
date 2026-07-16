<?php

namespace HiEvents\Resources\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\EventLocation\EventLocationResourcePublic;
use Illuminate\Http\Request;

/**
 * @mixin EventOccurrenceDomainObject
 */
class EventOccurrenceResourcePublic extends BaseResource
{
    public function __construct(
        $resource,
        private readonly bool $eventLevelShowCapacity = false,
        private readonly bool $includeOnlineConnectionDetails = false,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $showCapacity = $this->shouldShowAvailableCapacity($this->eventLevelShowCapacity);

        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'start_date' => $this->getStartDate(),
            'end_date' => $this->getEndDate(),
            'status' => $this->getStatus(),
            $this->mergeWhen($showCapacity, fn () => [
                'capacity' => $this->getCapacity(),
                'available_capacity' => $this->getAvailableCapacity(),
            ]),
            'label' => $this->getLabel(),
            'is_past' => $this->isPast(),
            'is_future' => $this->isFuture(),
            'is_active' => $this->isActive(),
            'event_location' => $this->when(
                condition: $this->getEventLocation() !== null,
                value: fn () => new EventLocationResourcePublic($this->getEventLocation(), $this->includeOnlineConnectionDetails),
            ),
        ];
    }
}
