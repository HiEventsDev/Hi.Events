<?php

namespace HiEvents\Services\Application\Handlers\User\DTO;

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
