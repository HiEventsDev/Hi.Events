<?php

namespace HiEvents\Services\Application\Handlers\Reports\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\OrganizerReportTypes;

class GetOrganizerReportDTO extends BaseDataObject
{
    public function __construct(
        public readonly int                   $organizerId,
        public readonly OrganizerReportTypes  $reportType,
        public readonly ?string               $startDate,
        public readonly ?string               $endDate,
        public readonly ?string               $currency,
    )
    {
    }
}
