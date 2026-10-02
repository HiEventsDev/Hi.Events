<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SeatSelectionDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $seat_uid,
        public readonly int $event_occurrence_id,
        public readonly int $product_id,
        public readonly int $product_price_id,
        public readonly ?int $order_item_id = null,
        public readonly ?int $attendee_id = null,
    ) {}
}
