<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public\DTO;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class CreateAttendeeCheckInPublicDTO extends Data
{
    public function __construct(
        public string     $checkInListUuid,
        public string     $checkInUserIpAddress,
        #[DataCollectionOf(AttendeeAndActionDTO::class)]
        public Collection $attendeesAndActions,
    )
    {
    }
}
