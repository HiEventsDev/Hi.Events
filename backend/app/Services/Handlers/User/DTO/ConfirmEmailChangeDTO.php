<?php

namespace HiEvents\Services\Handlers\User\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class ConfirmEmailChangeDTO extends BaseDTO
{
    public function __construct(
        public string $token,
        public int $accountId,
    )
    {
    }
}
