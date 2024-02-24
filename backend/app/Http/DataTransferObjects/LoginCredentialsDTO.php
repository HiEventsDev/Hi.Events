<?php

namespace HiEvents\Http\DataTransferObjects;

use HiEvents\DataTransferObjects\BaseDTO;

class LoginCredentialsDTO extends BaseDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    )
    {
    }
}
