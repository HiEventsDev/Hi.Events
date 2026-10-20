<?php

namespace Tests\Unit\Enterprise\Licensing;

use Carbon\CarbonImmutable;
use HiEvents\Enterprise\Licensing\DTO\LicencePayloadDTO;
use HiEvents\Enterprise\Licensing\DTO\LicenceSalesPeriodDTO;
use HiEvents\Enterprise\Licensing\DTO\LicenceStateDTO;
use HiEvents\Enterprise\Licensing\LicenceSalesService;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicenceStatus;
use HiEvents\Enterprise\Licensing\Plan;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LicenceSalesServiceTest extends TestCase
{
    private OrderRepositoryInterface&MockInterface $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderRepository->shouldReceive('getNetSalesByCurrency')->andReturn(collect());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_a_multi_year_key_reports_the_licence_year_containing_today_and_the_one_before(): void
    {
        CarbonImmutable::setTestNow('2027-03-15 09:00:00');

        $periods = $this->service(expiresAt: '2028-10-01', coveredSales: 300000)->salesByLicenceYear();

        $this->assertSame(
            [['2026-10-01', '2027-10-01', true], ['2025-10-01', '2026-10-01', false]],
            array_map(static fn (LicenceSalesPeriodDTO $period) => [
                $period->starts_at->toDateString(),
                $period->ends_at->toDateString(),
                $period->is_current,
            ], $periods),
        );
    }

    public function test_each_period_queries_sales_for_exactly_its_own_dates(): void
    {
        CarbonImmutable::setTestNow('2027-03-15 09:00:00');
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->expectSalesQuery('2026-10-01', '2027-10-01');
        $this->expectSalesQuery('2025-10-01', '2026-10-01');

        $this->service(expiresAt: '2027-10-01', coveredSales: 300000)->salesByLicenceYear();
    }

    public function test_a_key_expiring_on_29_february_keeps_its_years_back_to_back(): void
    {
        CarbonImmutable::setTestNow('2027-06-01 09:00:00');

        $periods = $this->service(expiresAt: '2028-02-29', coveredSales: 300000)->salesByLicenceYear();

        $this->assertSame(
            [['2027-02-28', '2028-02-29'], ['2026-02-28', '2027-02-28']],
            array_map(static fn (LicenceSalesPeriodDTO $period) => [
                $period->starts_at->toDateString(),
                $period->ends_at->toDateString(),
            ], $periods),
        );
    }

    public function test_the_new_licence_year_starts_on_the_anniversary_of_the_expiry_date(): void
    {
        CarbonImmutable::setTestNow('2027-10-01 00:00:00');

        $periods = $this->service(expiresAt: '2028-10-01', coveredSales: 300000)->salesByLicenceYear();

        $this->assertSame('2027-10-01', $periods[0]->starts_at->toDateString());
        $this->assertTrue($periods[0]->is_current);
    }

    public function test_a_licence_without_covered_sales_reports_nothing(): void
    {
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderRepository->shouldNotReceive('getNetSalesByCurrency');

        $this->assertSame([], $this->service(expiresAt: '2028-10-01', coveredSales: null)->salesByLicenceYear());
    }

    private function expectSalesQuery(string $from, string $to): void
    {
        $this->orderRepository->shouldReceive('getNetSalesByCurrency')
            ->once()
            ->with(
                Mockery::on(static fn (CarbonImmutable $date) => $date->toDateTimeString() === "$from 00:00:00"),
                Mockery::on(static fn (CarbonImmutable $date) => $date->toDateTimeString() === "$to 00:00:00"),
            )
            ->andReturn(collect());
    }

    private function service(string $expiresAt, ?int $coveredSales): LicenceSalesService
    {
        $licenceService = Mockery::mock(LicenceService::class);
        $licenceService->shouldReceive('state')->andReturn(new LicenceStateDTO(
            status: LicenceStatus::ACTIVE,
            features: [],
            licence: new LicencePayloadDTO(
                lid: 'lic_test',
                customer: 'Riverside Theatre',
                plan: Plan::OWN_EVENTS,
                features: [],
                issued_at: CarbonImmutable::parse('2025-09-20', 'UTC'),
                expires_at: CarbonImmutable::parse($expiresAt, 'UTC'),
                covered_sales: $coveredSales,
            ),
        ));

        return new LicenceSalesService($licenceService, $this->orderRepository);
    }
}
