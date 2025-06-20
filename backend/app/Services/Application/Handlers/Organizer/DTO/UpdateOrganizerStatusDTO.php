<?php

namespace HiEvents\Services\Application\Handlers\Organizer\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class UpdateOrganizerStatusDTO extends BaseDTO
{
    public function __construct(
        public string $status,
        public int $organizerId,
        public int $accountId,
    )
    {
    }
}