<?php

namespace HiEvents\Services\Domain\Report\Factory;

use HiEvents\DomainObjects\Enums\OrganizerReportTypes;
use HiEvents\Services\Domain\Report\AbstractOrganizerReportService;
use HiEvents\Services\Domain\Report\OrganizerReports\CheckInSummaryReport;
use HiEvents\Services\Domain\Report\OrganizerReports\EventsPerformanceReport;
use HiEvents\Services\Domain\Report\OrganizerReports\PlatformFeesReport;
use HiEvents\Services\Domain\Report\OrganizerReports\RevenueSummaryReport;
use HiEvents\Services\Domain\Report\OrganizerReports\TaxSummaryReport;
use Illuminate\Support\Facades\App;

class OrganizerReportServiceFactory
{
    public function create(OrganizerReportTypes $reportType): AbstractOrganizerReportService|PlatformFeesReport
    {
        return match ($reportType) {
            OrganizerReportTypes::REVENUE_SUMMARY => App::make(RevenueSummaryReport::class),
            OrganizerReportTypes::EVENTS_PERFORMANCE => App::make(EventsPerformanceReport::class),
            OrganizerReportTypes::TAX_SUMMARY => App::make(TaxSummaryReport::class),
            OrganizerReportTypes::CHECK_IN_SUMMARY => App::make(CheckInSummaryReport::class),
            OrganizerReportTypes::PLATFORM_FEES => App::make(PlatformFeesReport::class),
        };
    }
}
