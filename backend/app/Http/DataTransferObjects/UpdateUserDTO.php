<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\DomainObjects\Status\UserStatus;
use TicketKitten\DataTransferObjects\BaseDTO;

class UpdateUserDTO extends BaseDTO
{
    public function __construct(
        public readonly int        $id,
        public readonly int        $account_id,
        public readonly string     $first_name,
        public readonly string     $last_name,
        public readonly Role       $role,
        public readonly UserStatus $status,
        public readonly int        $updated_by_user_id,
    )
    {
    }
}
