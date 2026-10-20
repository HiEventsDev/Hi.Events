<?php

namespace HiEvents\Services\Application\Handlers\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BulkUpdateOccurrencesResultDTO extends BaseDataObject
{
    /**
     * @param  int[]  $updated_ids
     */
    public function __construct(
        public readonly int $updated_count,
        public readonly array $updated_ids,
    ) {}
}
