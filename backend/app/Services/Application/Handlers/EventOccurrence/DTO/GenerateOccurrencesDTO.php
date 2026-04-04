<?php

namespace HiEvents\Services\Application\Handlers\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GenerateOccurrencesDTO extends BaseDataObject
{
    public function __construct(
        public readonly int   $event_id,
        public readonly array $recurrence_rule,
    )
    {
    }
}
