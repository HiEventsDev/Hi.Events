<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SeatMapDiffDTO extends BaseDataObject
{
    /**
     * @param  string[]  $removed_seat_labels
     */
    public function __construct(
        public readonly int $added_seat_count,
        public readonly array $removed_seat_labels,
        public readonly int $relabelled_seat_count,
        public readonly int $rebanded_seat_count,
    ) {}
}
