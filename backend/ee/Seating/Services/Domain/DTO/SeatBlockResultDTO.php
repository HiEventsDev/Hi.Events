<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SeatBlockResultDTO extends BaseDataObject
{
    /**
     * @param  SkippedSeatBlockDTO[]  $skipped
     */
    public function __construct(
        public readonly int $blocked,
        public readonly array $skipped,
    ) {}
}
