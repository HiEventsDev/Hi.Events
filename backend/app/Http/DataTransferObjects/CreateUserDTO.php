<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\DataTransferObjects\BaseDTO;

class CreateUserDTO extends BaseDTO
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $email,
        public int    $invited_by,
        public int    $account_id,
        public Role   $role,
    )
    {
    }
}
