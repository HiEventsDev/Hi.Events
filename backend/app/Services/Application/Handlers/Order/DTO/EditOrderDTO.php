<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class EditOrderDTO extends BaseDTO
{
    public function __construct(
        public int $id,
        public string $first_name,
        public string $last_name,
        public string $email,
        public ?string $notes,
    )
    {
    }
}
