<?php

namespace HiEvents\Services\Handlers\Auth\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class LoginCredentialsDTO extends BaseDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly ?int $accountId = null,
    )
    {
    }
}
