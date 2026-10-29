<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EventCountsResponseDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $total_orders,
        public readonly int $total_attendees_registered,
    ) {}
}
