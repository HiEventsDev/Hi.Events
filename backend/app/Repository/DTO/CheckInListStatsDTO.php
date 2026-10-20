<?php

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CheckInListStatsDTO extends BaseDTO
{
    /**
     * @param  CheckInListProductStatDTO[]  $perProduct
     * @param  CheckInListRecentCheckInDTO[]  $recentCheckIns
     */
    public function __construct(
        public int $totalAttendees,
        public int $checkedInAttendees,
        public array $perProduct,
        public array $recentCheckIns,
    ) {}
}
