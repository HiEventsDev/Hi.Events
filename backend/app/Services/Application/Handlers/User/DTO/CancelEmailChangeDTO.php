<?php

namespace HiEvents\Services\Application\Handlers\User\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CancelEmailChangeDTO extends BaseDTO
{
    public function __construct(
        public int $userId,
        public int $accountId,
    )
    {
    }
}
