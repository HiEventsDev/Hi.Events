<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class StartImpersonationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $userId,
        public readonly int $accountId,
        public readonly int $impersonatorId,
    )
    {
    }
}
