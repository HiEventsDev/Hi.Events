<?php

namespace HiEvents\Services\Application\Handlers\Announcement\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetActiveAnnouncementsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $userId,
        public readonly int $accountId,
    ) {}
}
