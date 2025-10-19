<?php

namespace HiEvents\Resources\CheckInList;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\Resources\Attendee\AttendeeResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendeeCheckInDomainObject
 */
class AttendeeCheckInResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'attendee_id' => $this->getAttendeeId(),
            'check_in_list_id' => $this->getCheckInListId(),
            'product_id' => $this->getProductId(),
            'event_id' => $this->getEventId(),
            'short_id' => $this->getShortId(),
            'created_at' => $this->getCreatedAt(),
            'check_in_list' => $this->when(
                !is_null($this->getCheckInList()),
                fn() => (new CheckInListResource($this->getCheckInList()))->toArray($request)
            ),
            'attendee' => $this->when(
                !is_null($this->getAttendee()),
                fn() => (new AttendeeResource($this->getAttendee()))->toArray($request)
            ),
        ];
    }
}
