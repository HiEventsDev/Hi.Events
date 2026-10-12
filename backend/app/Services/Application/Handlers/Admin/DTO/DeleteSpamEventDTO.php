<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class DeleteSpamEventDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $eventId,
        public readonly int $reviewedByUserId,
        public readonly bool $deleteAccount,
    ) {}
}
