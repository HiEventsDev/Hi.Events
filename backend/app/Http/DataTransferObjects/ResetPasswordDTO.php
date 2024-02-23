<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DataTransferObjects\BaseDTO;

class ResetPasswordDTO extends BaseDTO
{
    public function __construct(
        public readonly string $token,
        public readonly string $password,
        public readonly string $currentPassword,
        public readonly string $ipAddress,
        public readonly string $userAgent,
    )
    {
    }
}
