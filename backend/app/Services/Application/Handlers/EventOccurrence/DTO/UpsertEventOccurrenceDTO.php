<?php

namespace HiEvents\Services\Application\Handlers\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertEventOccurrenceDTO extends BaseDataObject
{
    public function __construct(
        public readonly int     $event_id,
        public readonly string  $start_date,
        public readonly ?string $end_date = null,
        public readonly ?string $status = null,
        public readonly ?int    $capacity = null,
        public readonly ?string $label = null,
        public readonly bool    $is_overridden = false,
        public readonly ?int    $id = null,
    )
    {
    }
}
