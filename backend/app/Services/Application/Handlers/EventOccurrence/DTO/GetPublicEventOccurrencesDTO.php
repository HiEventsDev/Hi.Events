<?php

namespace HiEvents\Services\Application\Handlers\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetPublicEventOccurrencesDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $eventId,
        public readonly ?string $startDateFrom,
        public readonly ?string $startDateTo,
    ) {}
}
