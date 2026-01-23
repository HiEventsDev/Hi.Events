<?php

namespace HiEvents\Services\Application\Handlers\Reports;

use HiEvents\Services\Application\Handlers\Reports\DTO\GetOrganizerReportDTO;
use HiEvents\Services\Domain\Report\DTO\PaginatedReportDTO;
use HiEvents\Services\Domain\Report\Factory\OrganizerReportServiceFactory;
use HiEvents\Services\Domain\Report\OrganizerReports\PlatformFeesReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetOrganizerReportHandler
{
    public function __construct(
        private readonly OrganizerReportServiceFactory $reportServiceFactory,
    )
    {
    }

    public function handle(GetOrganizerReportDTO $reportData): Collection|PaginatedReportDTO
    {
        $reportService = $this->reportServiceFactory->create($reportData->reportType);

        if ($reportService instanceof PlatformFeesReport) {
            return $reportService->generateReport(
                organizerId: $reportData->organizerId,
                currency: $reportData->currency,
                startDate: $reportData->startDate ? Carbon::parse($reportData->startDate) : null,
                endDate: $reportData->endDate ? Carbon::parse($reportData->endDate) : null,
                eventId: $reportData->eventId,
                page: $reportData->page,
                perPage: $reportData->perPage,
            );
        }

        return $reportService->generateReport(
            organizerId: $reportData->organizerId,
            currency: $reportData->currency,
            startDate: $reportData->startDate ? Carbon::parse($reportData->startDate) : null,
            endDate: $reportData->endDate ? Carbon::parse($reportData->endDate) : null,
        );
    }
}
