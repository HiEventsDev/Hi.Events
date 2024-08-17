<?php

namespace HiEvents\Services\Domain\CheckInList\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DataTransferObjects\ErrorBagDTO;
use Illuminate\Support\Collection;

class CreateAttendeeCheckInsResponseDTO extends BaseDTO
{
    public function __construct(
        public Collection  $attendeeCheckIns,
        public ErrorBagDTO $errors,
    )
    {
    }
}
