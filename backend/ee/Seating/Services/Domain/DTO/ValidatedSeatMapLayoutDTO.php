<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ValidatedSeatMapLayoutDTO extends BaseDataObject
{
    public function __construct(
        public readonly array $layout,
        public readonly int $seat_count,
    ) {}
}
