<?php

namespace HiEvents\Services\Handlers\Reports;

use HiEvents\Services\Domain\Report\Factory\ReportServiceFactory;
use HiEvents\Services\Handlers\Reports\DTO\GetReportDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetReportHandler
{
    public function __construct(
        private readonly ReportServiceFactory $reportServiceFactory,
    )
    {
    }

    public function handle(GetReportDTO $reportData): Collection
    {
        return $this->reportServiceFactory
            ->create($reportData->reportType)
            ->generateReport(
                eventId: $reportData->eventId,
                startDate: $reportData->startDate ? Carbon::parse($reportData->startDate) : null,
                endDate: $reportData->endDate ? Carbon::parse($reportData->endDate) : null,
            );
    }
}
