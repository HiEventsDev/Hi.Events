<?php

declare(strict_types=1);

namespace HiEvents\Resources\Lineup;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Application\Handlers\Lineup\DTO\EventLineupDTO;
use Illuminate\Http\Request;

/**
 * @mixin EventLineupDTO
 */
class EventLineupResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'entries' => LineupEntryResourcePublic::collectionWithAdditionalData($this->entries, [
                LineupEntryResourcePublic::SCHEDULE_DAYS_BY_ENTRY_ID => $this->schedule_days_by_entry_id,
            ]),
            'spaces' => EventSpaceResourcePublic::collection($this->spaces),
            'schedule_items' => ScheduleItemResource::collection($this->schedule_items),
        ];
    }
}
