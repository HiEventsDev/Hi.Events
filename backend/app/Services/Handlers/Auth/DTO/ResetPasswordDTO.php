<?php

namespace HiEvents\Services\Handlers\Auth\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class ResetPasswordDTO extends BaseDTO
{
    public function __construct(
        public readonly string $token,
        public readonly string $password,
        public readonly string $ipAddress,
        public readonly string $userAgent,
    )
    {
    }
}
