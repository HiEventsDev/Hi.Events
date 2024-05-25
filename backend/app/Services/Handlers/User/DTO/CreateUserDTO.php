<?php

namespace HiEvents\Services\Handlers\User\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\Role;

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
