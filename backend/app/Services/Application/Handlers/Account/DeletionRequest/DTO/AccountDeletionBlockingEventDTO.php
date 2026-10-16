<?php

namespace HiEvents\Services\Application\Handlers\Account\DeletionRequest\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class AccountDeletionBlockingEventDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
    ) {}
}
