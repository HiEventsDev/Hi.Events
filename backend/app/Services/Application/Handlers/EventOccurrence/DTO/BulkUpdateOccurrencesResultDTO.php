<?php

namespace HiEvents\Services\Application\Handlers\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BulkUpdateOccurrencesResultDTO extends BaseDataObject
{
    /**
     * @param  int[]  $updated_ids  Server-side resolved ids of the occurrences
     *                              actually affected. Callers chaining a follow-up notification should use
     *                              these rather than the request's `occurrence_ids` so an `apply_to_all`
     *                              request can still target the correct attendees.
     */
    public function __construct(
        public readonly int $updated_count,
        public readonly array $updated_ids,
    ) {}
}
