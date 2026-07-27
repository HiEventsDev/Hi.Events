<?php

namespace HiEvents\Services\Application\Handlers\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\EventDomainObject;
use Illuminate\Support\Collection;

class GetPublicEventOccurrencesResultDTO extends BaseDataObject
{
    public function __construct(
        public readonly EventDomainObject $event,
        public readonly Collection $occurrences,
    ) {}
}
