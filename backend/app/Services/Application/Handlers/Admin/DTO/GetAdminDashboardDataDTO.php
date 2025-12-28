<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetAdminDashboardDataDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $days = 14,
        public readonly int $limit = 10,
    ) {
    }
}
