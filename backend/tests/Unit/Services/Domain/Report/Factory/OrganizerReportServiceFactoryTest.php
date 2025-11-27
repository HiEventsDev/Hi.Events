<?php

namespace Tests\Unit\Services\Domain\Report\Factory;

use HiEvents\DomainObjects\Enums\OrganizerReportTypes;
use HiEvents\Services\Domain\Report\Factory\OrganizerReportServiceFactory;
use HiEvents\Services\Domain\Report\OrganizerReports\EventsPerformanceReport;
use HiEvents\Services\Domain\Report\OrganizerReports\RevenueSummaryReport;
use HiEvents\Services\Domain\Report\OrganizerReports\TaxSummaryReport;
use HiEvents\Services\Domain\Report\OrganizerReports\CheckInSummaryReport;
use Tests\TestCase;

class OrganizerReportServiceFactoryTest extends TestCase
{
    private OrganizerReportServiceFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new OrganizerReportServiceFactory();
    }

    public function testCreateReturnsRevenueSummaryReport(): void
    {
        $reportService = $this->factory->create(OrganizerReportTypes::REVENUE_SUMMARY);

        $this->assertInstanceOf(RevenueSummaryReport::class, $reportService);
    }

    public function testCreateReturnsEventsPerformanceReport(): void
    {
        $reportService = $this->factory->create(OrganizerReportTypes::EVENTS_PERFORMANCE);

        $this->assertInstanceOf(EventsPerformanceReport::class, $reportService);
    }

    public function testCreateReturnsTaxSummaryReport(): void
    {
        $reportService = $this->factory->create(OrganizerReportTypes::TAX_SUMMARY);

        $this->assertInstanceOf(TaxSummaryReport::class, $reportService);
    }

    public function testCreateReturnsCheckInSummaryReport(): void
    {
        $reportService = $this->factory->create(OrganizerReportTypes::CHECK_IN_SUMMARY);

        $this->assertInstanceOf(CheckInSummaryReport::class, $reportService);
    }
}
