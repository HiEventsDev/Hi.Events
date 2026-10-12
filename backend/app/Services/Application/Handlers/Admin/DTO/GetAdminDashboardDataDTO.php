<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\AdminDashboardPeriod;

class GetAdminDashboardDataDTO extends BaseDataObject
{
    public function __construct(
        public readonly AdminDashboardPeriod $period = AdminDashboardPeriod::LAST_7_DAYS,
        public readonly int $limit = 10,
    ) {}
}
