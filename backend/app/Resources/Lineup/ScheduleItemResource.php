<?php

declare(strict_types=1);

namespace HiEvents\Resources\Lineup;

use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin EventScheduleItemDomainObject
 */
class ScheduleItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'starts_at' => $this->getStartsAt(),
            'ends_at' => $this->getEndsAt(),
            'event_space_id' => $this->getEventSpaceId(),
            'lineup_entry_ids' => ($this->getPerformers() ?? collect())
                ->map(fn (EventScheduleItemPerformerDomainObject $performer) => $performer->getEventLineupEntryId())
                ->values()
                ->all(),
        ];
    }
}
