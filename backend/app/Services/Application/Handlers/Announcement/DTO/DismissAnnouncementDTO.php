<?php

namespace HiEvents\Services\Application\Handlers\Announcement\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class DismissAnnouncementDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $announcementId,
        public readonly int $userId,
    ) {}
}
