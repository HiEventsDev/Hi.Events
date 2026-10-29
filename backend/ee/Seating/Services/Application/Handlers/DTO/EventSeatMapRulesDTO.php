<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EventSeatMapRulesDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $prevent_orphan_seats,
        public readonly ?int $max_seats_per_order,
        public readonly bool $allow_seat_change,
    ) {}
}
