<?php

namespace HiEvents\Resources\CheckInList;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendeeCheckInDomainObject
 */
class AttendeeCheckInPublicResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'check_in_list_id' => $this->getCheckInListId(),
            'attendee_id' => $this->getAttendeeId(),
            'checked_in_at' => $this->getCreatedAt(),
        ];
    }
}
