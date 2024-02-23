<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DataTransferObjects\BaseDTO;

class LoginCredentialsDTO extends BaseDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    )
    {
    }
}
