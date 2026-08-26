<?php

namespace HiEvents\Services\Application\Handlers\Announcement\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetAllAnnouncementsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $perPage = 20,
        public readonly ?string $search = null,
    ) {}
}
