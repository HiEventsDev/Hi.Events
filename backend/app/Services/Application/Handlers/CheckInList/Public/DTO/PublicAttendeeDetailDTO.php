<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use Illuminate\Support\Collection;

class PublicAttendeeDetailDTO extends BaseDTO
{
    /**
     * @param  Collection<int, AttendeeCheckInDomainObject>  $currentListCheckIns
     */
    public function __construct(
        public AttendeeDomainObject $attendee,
        public Collection $currentListCheckIns,
        public bool $showNotes,
        public bool $showQuestionAnswers,
        public bool $showOrderDetails,
    ) {}
}
