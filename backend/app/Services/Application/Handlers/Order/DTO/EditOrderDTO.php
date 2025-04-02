<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class EditOrderDTO extends BaseDTO
{
    public function __construct(
        public int     $id,
        public int     $eventId,
        public string  $firstName,
        public string  $lastName,
        public string  $email,
        public ?string $notes,
    )
    {
    }
}
