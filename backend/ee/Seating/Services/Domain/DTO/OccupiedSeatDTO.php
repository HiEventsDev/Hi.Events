<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Status\SeatClaimStatus;

class OccupiedSeatDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $seat_uid,
        public readonly string $seat_label,
        public readonly bool $is_zone,
        public readonly string $band_key,
        public readonly SeatClaimStatus $status,
        public readonly ?string $block_reason,
        public readonly ?string $attendee_public_id,
        public readonly ?string $attendee_name,
    ) {}
}
