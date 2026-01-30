<?php

namespace HiEvents\Services\Application\Handlers\EventSettings\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetPlatformFeePreviewDTO extends BaseDataObject
{
    public function __construct(
        public readonly int   $eventId,
        public readonly float $price,
    ) {
    }
}
