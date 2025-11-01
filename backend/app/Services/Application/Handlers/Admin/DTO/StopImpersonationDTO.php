<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class StopImpersonationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $impersonatorId,
    )
    {
    }
}
