<?php

namespace HiEvents\Http\DataTransferObjects;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DataTransferObjects\BaseDTO;

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
