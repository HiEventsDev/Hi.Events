<?php

namespace Tests\Unit\Services\Application\Handlers\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\DTO\BoxOfficeSummaryRowDTO;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\GetBoxOfficeStatsDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\GetBoxOfficeStatsHandler;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetBoxOfficeStatsHandlerTest extends TestCase
{
    private MockInterface|BoxOfficeRepositoryInterface $boxOfficeRepository;

    private MockInterface|OrderRepositoryInterface $orderRepository;

    private GetBoxOfficeStatsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->boxOfficeRepository = Mockery::mock(BoxOfficeRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->handler = new GetBoxOfficeStatsHandler($this->boxOfficeRepository, $this->orderRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_aggregates_rows_by_tender_operator_and_day(): void
    {
        $event = (new EventDomainObject)->setTimezone('Europe/Dublin')->setCurrency('EUR');
        $boxOffice = (new BoxOfficeDomainObject)->setId(3)->setEvent($event);

        $this->boxOfficeRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($boxOffice);
        $this->orderRepository
            ->shouldReceive('getBoxOfficeSummary')
            ->once()
            ->with(3, 'Europe/Dublin', null, null)
            ->andReturn(collect([
                new BoxOfficeSummaryRowDTO(tender: 'CASH', operatorName: 'Sam', day: '2026-09-06', orders: 2, gross: 40.0, refunded: 0.0),
                new BoxOfficeSummaryRowDTO(tender: 'CARD', operatorName: 'Sam', day: '2026-09-06', orders: 1, gross: 25.5, refunded: 5.0),
                new BoxOfficeSummaryRowDTO(tender: 'CASH', operatorName: 'Alex', day: '2026-09-07', orders: 1, gross: 10.0, refunded: 0.0),
            ]));

        $stats = $this->handler->handle(new GetBoxOfficeStatsDTO(event_id: 7, box_office_id: 3));

        $this->assertSame('EUR', $stats->currency);
        $this->assertSame(4, $stats->orders);
        $this->assertSame(75.5, $stats->gross);
        $this->assertSame(5.0, $stats->refunded);
        $this->assertSame([
            ['tender' => 'CASH', 'orders' => 3, 'gross' => 50.0, 'refunded' => 0.0],
            ['tender' => 'CARD', 'orders' => 1, 'gross' => 25.5, 'refunded' => 5.0],
        ], $stats->by_tender);
        $this->assertSame([
            ['operator_name' => 'Sam', 'orders' => 3, 'gross' => 65.5, 'refunded' => 5.0],
            ['operator_name' => 'Alex', 'orders' => 1, 'gross' => 10.0, 'refunded' => 0.0],
        ], $stats->by_operator);
        $this->assertCount(2, $stats->by_day);
        $this->assertSame('2026-09-06', $stats->by_day[0]['day']);
    }

    public function test_date_range_covers_whole_days_in_the_event_timezone(): void
    {
        $boxOffice = (new BoxOfficeDomainObject)->setId(3)->setEventId(7);
        $boxOffice->setEvent((new EventDomainObject)->setId(7)->setTimezone('Europe/Dublin')->setCurrency('EUR'));
        $this->boxOfficeRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($boxOffice);
        $this->orderRepository
            ->shouldReceive('getBoxOfficeSummary')
            ->once()
            ->with(3, 'Europe/Dublin', '2026-09-05 23:00:00', '2026-09-06 22:59:59')
            ->andReturn(collect());

        $stats = $this->handler->handle(new GetBoxOfficeStatsDTO(event_id: 7, box_office_id: 3, from: '2026-09-06', to: '2026-09-06'));

        $this->assertSame(0, $stats->orders);
    }
}
