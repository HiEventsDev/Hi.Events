<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public\DTO;

use HiEvents\DomainObjects\Enums\AttendeeCheckInActionType;
use Spatie\LaravelData\Data;

class AttendeeAndActionDTO extends Data
{
    public function __construct(
        public string                    $public_id,
        public AttendeeCheckInActionType $action,
    )
    {
    }
}
