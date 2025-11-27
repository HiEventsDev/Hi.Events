<?php

namespace Tests\Unit\Services\Application\Handlers\Reports;

use HiEvents\DomainObjects\Enums\OrganizerReportTypes;
use HiEvents\Services\Application\Handlers\Reports\DTO\GetOrganizerReportDTO;
use HiEvents\Services\Application\Handlers\Reports\GetOrganizerReportHandler;
use HiEvents\Services\Domain\Report\AbstractOrganizerReportService;
use HiEvents\Services\Domain\Report\Factory\OrganizerReportServiceFactory;
use Mockery as m;
use Tests\TestCase;

class GetOrganizerReportHandlerTest extends TestCase
{
    private OrganizerReportServiceFactory $reportServiceFactory;
    private GetOrganizerReportHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportServiceFactory = m::mock(OrganizerReportServiceFactory::class);
        $this->handler = new GetOrganizerReportHandler($this->reportServiceFactory);
    }

    public function testHandleReturnsReportData(): void
    {
        $organizerId = 1;
        $reportType = OrganizerReportTypes::REVENUE_SUMMARY;
        $startDate = '2024-01-01';
        $endDate = '2024-01-31';
        $currency = 'USD';

        $dto = new GetOrganizerReportDTO(
            organizerId: $organizerId,
            reportType: $reportType,
            startDate: $startDate,
            endDate: $endDate,
            currency: $currency,
        );

        $expectedCollection = collect([
            ['date' => '2024-01-01', 'gross_sales' => 100.00],
            ['date' => '2024-01-02', 'gross_sales' => 200.00],
        ]);

        $mockReportService = m::mock(AbstractOrganizerReportService::class);
        $mockReportService
            ->shouldReceive('generateReport')
            ->once()
            ->withArgs(function ($orgId, $curr, $start, $end) use ($organizerId, $currency) {
                return $orgId === $organizerId
                    && $curr === $currency
                    && $start->toDateString() === '2024-01-01'
                    && $end->toDateString() === '2024-01-31';
            })
            ->andReturn($expectedCollection);

        $this->reportServiceFactory
            ->shouldReceive('create')
            ->once()
            ->with($reportType)
            ->andReturn($mockReportService);

        $result = $this->handler->handle($dto);

        $this->assertEquals($expectedCollection, $result);
    }

    public function testHandleWorksWithNullDates(): void
    {
        $organizerId = 1;
        $reportType = OrganizerReportTypes::EVENTS_PERFORMANCE;

        $dto = new GetOrganizerReportDTO(
            organizerId: $organizerId,
            reportType: $reportType,
            startDate: null,
            endDate: null,
            currency: null,
        );

        $expectedCollection = collect([]);

        $mockReportService = m::mock(AbstractOrganizerReportService::class);
        $mockReportService
            ->shouldReceive('generateReport')
            ->once()
            ->withArgs(function ($orgId, $curr, $start, $end) use ($organizerId) {
                return $orgId === $organizerId
                    && $curr === null
                    && $start === null
                    && $end === null;
            })
            ->andReturn($expectedCollection);

        $this->reportServiceFactory
            ->shouldReceive('create')
            ->once()
            ->with($reportType)
            ->andReturn($mockReportService);

        $this->handler->handle($dto);

        $this->assertTrue(true);
    }

    public function testHandleCorrectlyRoutesToTaxSummaryReport(): void
    {
        $organizerId = 2;
        $reportType = OrganizerReportTypes::TAX_SUMMARY;

        $dto = new GetOrganizerReportDTO(
            organizerId: $organizerId,
            reportType: $reportType,
            startDate: '2024-06-01',
            endDate: '2024-06-30',
            currency: 'EUR',
        );

        $expectedCollection = collect([
            ['tax_name' => 'VAT', 'rate' => 20, 'total_collected' => 500.00],
        ]);

        $mockReportService = m::mock(AbstractOrganizerReportService::class);
        $mockReportService
            ->shouldReceive('generateReport')
            ->once()
            ->andReturn($expectedCollection);

        $this->reportServiceFactory
            ->shouldReceive('create')
            ->once()
            ->with($reportType)
            ->andReturn($mockReportService);

        $result = $this->handler->handle($dto);

        $this->assertEquals($expectedCollection, $result);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
