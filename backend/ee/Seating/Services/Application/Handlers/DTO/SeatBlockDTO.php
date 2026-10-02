<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SeatBlockDTO extends BaseDataObject
{
    /**
     * @param  int[]  $event_occurrence_ids
     * @param  string[]  $seat_uids
     */
    public function __construct(
        public readonly int $event_id,
        public readonly bool $all_upcoming_dates,
        public readonly array $event_occurrence_ids,
        public readonly array $seat_uids,
        public readonly ?string $reason,
    ) {}
}
