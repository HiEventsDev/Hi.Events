<?php

namespace HiEvents\Services\Application\Handlers\Organizer\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class DeleteOrganizerDTO extends BaseDTO
{
    public function __construct(
        public int $organizerId,
        public int $accountId,
    )
    {
    }
}
