<?php

namespace HiEvents\Services\Application\Handlers\Event\DTO;

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
