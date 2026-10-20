<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BoxOfficeBestAvailableSeatsDTO extends BaseDataObject
{
    /**
     * @param  BoxOfficeSeatRequestItemDTO[]  $items
     * @param  string[]  $excluded_seat_uids
     */
    public function __construct(
        public readonly int $event_id,
        public readonly int $event_occurrence_id,
        public readonly array $items,
        public readonly array $excluded_seat_uids,
    ) {}
}
