<?php

namespace Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\Enums\AttributionGroupBy;
use HiEvents\Repository\Interfaces\AccountAttributionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetUtmAttributionStatsDTO;
use HiEvents\Services\Application\Handlers\Admin\GetUtmAttributionStatsHandler;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class GetUtmAttributionStatsHandlerTest extends TestCase
{
    public function test_dates_are_normalised_to_utc(): void
    {
        $this->assertDatesPassedToRepository(
            dateFrom: '2026-09-04 10:30:00',
            dateTo: '2026-09-05T10:30:00+02:00',
            expectedFrom: '2026-09-04 10:30:00',
            expectedTo: '2026-09-05 08:30:00',
        );
    }

    public function test_missing_dates_stay_null(): void
    {
        $this->assertDatesPassedToRepository(null, null, null, null);
    }

    private function assertDatesPassedToRepository(?string $dateFrom, ?string $dateTo, ?string $expectedFrom, ?string $expectedTo): void
    {
        $repository = Mockery::mock(AccountAttributionRepositoryInterface::class);
        $paginator = new LengthAwarePaginator([], 0, 20);

        $repository->shouldReceive('getAttributionStats')
            ->once()
            ->withArgs(fn (AttributionGroupBy $groupBy, ?string $from, ?string $to) => $groupBy === AttributionGroupBy::CAMPAIGN && $from === $expectedFrom && $to === $expectedTo)
            ->andReturn($paginator);

        $repository->shouldReceive('getAttributionSummary')
            ->once()
            ->with($expectedFrom, $expectedTo)
            ->andReturn(['total_accounts' => 0]);

        $result = (new GetUtmAttributionStatsHandler($repository))->handle(new GetUtmAttributionStatsDTO(
            group_by: 'campaign',
            date_from: $dateFrom,
            date_to: $dateTo,
        ));

        $this->assertSame($paginator, $result['data']);
        $this->assertSame(['total_accounts' => 0], $result['summary']);
    }
}
