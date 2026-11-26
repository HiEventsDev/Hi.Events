<?php

namespace HiEvents\Services\Application\Handlers\TicketLookup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;

class LookupTicketDTO extends BaseDataObject
{
    public function __construct(
        public readonly AttendeeDomainObject $attendee,
        public readonly EventDomainObject $event,
    ) {}
}
