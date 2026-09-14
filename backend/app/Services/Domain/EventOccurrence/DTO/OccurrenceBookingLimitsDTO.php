<?php

namespace HiEvents\Services\Domain\EventOccurrence\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class OccurrenceBookingLimitsDTO extends BaseDataObject
{
    /**
     * @param  OccurrenceTierAllocationDTO[]  $allocations
     */
    public function __construct(
        public readonly ?int $capacity,
        public readonly ?int $allocation_total,
        public readonly ?int $sellable,
        public readonly array $allocations,
    ) {}
}
