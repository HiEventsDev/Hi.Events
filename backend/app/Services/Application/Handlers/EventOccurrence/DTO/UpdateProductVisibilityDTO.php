<?php

namespace HiEvents\Services\Application\Handlers\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpdateProductVisibilityDTO extends BaseDataObject
{
    public function __construct(
        public readonly int   $event_id,
        public readonly int   $event_occurrence_id,
        public readonly array $product_ids,
    )
    {
    }
}
