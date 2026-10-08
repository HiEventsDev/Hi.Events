<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BestAvailableSeatsRequestDTO extends BaseDataObject
{
    /**
     * @param  string[]  $excluded_seat_uids
     */
    public function __construct(
        public readonly int $event_id,
        public readonly int $event_occurrence_id,
        public readonly int $product_id,
        public readonly int $quantity,
        public readonly bool $accessible,
        public readonly array $excluded_seat_uids,
    ) {}
}
