<?php

namespace HiEvents\Services\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class DeleteEventImageDTO extends BaseDTO
{
    public function __construct(
        public int $eventId,
        public int $imageId,
    )
    {
    }
}
