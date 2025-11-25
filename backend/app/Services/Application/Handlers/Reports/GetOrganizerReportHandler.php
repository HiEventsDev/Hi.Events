<?php

namespace HiEvents\Services\Application\Handlers\Reports;

use HiEvents\Services\Application\Handlers\Reports\DTO\GetOrganizerReportDTO;
use HiEvents\Services\Domain\Report\Factory\OrganizerReportServiceFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetOrganizerReportHandler
{
    public function __construct(
        private readonly OrganizerReportServiceFactory $reportServiceFactory,
    )
    {
    }

    public function handle(GetOrganizerReportDTO $reportData): Collection
    {
        return $this->reportServiceFactory
            ->create($reportData->reportType)
            ->generateReport(
                organizerId: $reportData->organizerId,
                currency: $reportData->currency,
                startDate: $reportData->startDate ? Carbon::parse($reportData->startDate) : null,
                endDate: $reportData->endDate ? Carbon::parse($reportData->endDate) : null,
            );
    }
}
