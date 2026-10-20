<?php

namespace Tests\Unit\Services\Domain\Report\Factory;

use HiEvents\DomainObjects\Enums\OrganizerReportTypes;
use HiEvents\Services\Domain\Report\Factory\OrganizerReportServiceFactory;
use HiEvents\Services\Domain\Report\OrganizerReports\CheckInSummaryReport;
use HiEvents\Services\Domain\Report\OrganizerReports\EventsPerformanceReport;
use HiEvents\Services\Domain\Report\OrganizerReports\RevenueSummaryReport;
use HiEvents\Services\Domain\Report\OrganizerReports\TaxSummaryReport;
use Tests\TestCase;

class OrganizerReportServiceFactoryTest extends TestCase
{
    private OrganizerReportServiceFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new OrganizerReportServiceFactory;
    }

    public function test_create_returns_revenue_summary_report(): void
    {
        $reportService = $this->factory->create(OrganizerReportTypes::REVENUE_SUMMARY);

        $this->assertInstanceOf(RevenueSummaryReport::class, $reportService);
    }

    public function test_create_returns_events_performance_report(): void
    {
        $reportService = $this->factory->create(OrganizerReportTypes::EVENTS_PERFORMANCE);

        $this->assertInstanceOf(EventsPerformanceReport::class, $reportService);
    }

    public function test_create_returns_tax_summary_report(): void
    {
        $reportService = $this->factory->create(OrganizerReportTypes::TAX_SUMMARY);

        $this->assertInstanceOf(TaxSummaryReport::class, $reportService);
    }

    public function test_create_returns_check_in_summary_report(): void
    {
        $reportService = $this->factory->create(OrganizerReportTypes::CHECK_IN_SUMMARY);

        $this->assertInstanceOf(CheckInSummaryReport::class, $reportService);
    }
}
