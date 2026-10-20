<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SkippedSeatBlockDTO extends BaseDataObject
{
    /**
     * @param  string[]  $seat_uids
     */
    public function __construct(
        public readonly int $event_occurrence_id,
        public readonly array $seat_uids,
    ) {}
}
