<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Events\Stats\GetEventStatsAction;
use HiEvents\Http\Actions\Organizers\Stats\GetOrganizerStatsAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\GetEventStatsTool;
use HiEvents\Mcp\Tools\GetOrganizerStatsTool;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

class StatsToolsTest extends McpToolTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_event_stats_cover_the_whole_event_without_the_daily_series_by_default(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetEventStatsAction::class, ['event_id' => 12], ['date_range' => 'event'])
            ->andReturn($this->ok($this->eventStats()));

        $result = $this->structured((new GetEventStatsTool($this->api))->handle(new Request(['event_id' => 12])));

        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-02'], $result['period']);
        $this->assertSame(['total_products_sold' => 40, 'total_gross_sales' => 600, 'total_views' => 90], $result['stats']);
        $this->assertArrayNotHasKey('daily', $result);
        $this->assertArrayNotHasKey('date_id', $result);
    }

    public function test_event_stats_for_one_date_and_range_drop_views_and_include_daily_rows(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetEventStatsAction::class, ['event_id' => 12], ['start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'occurrence_id' => 7])
            ->andReturn($this->ok($this->eventStats()));

        $result = $this->structured((new GetEventStatsTool($this->api))->handle(new Request([
            'event_id' => 12, 'from' => '2026-10-01', 'to' => '2026-10-02', 'date_id' => 7, 'include_daily' => true,
        ])));

        $this->assertSame(7, $result['date_id']);
        $this->assertArrayNotHasKey('total_views', $result['stats']);
        $this->assertSame([
            ['date' => '2026-10-01', 'products_sold' => 15, 'total_sales_gross' => 225],
            ['date' => '2026-10-02', 'products_sold' => 25, 'total_sales_gross' => 375],
        ], $result['daily']);
    }

    public function test_event_stats_range_ends_today_when_only_from_is_given(): void
    {
        Carbon::setTestNow('2026-11-06 15:00:00');

        $this->api->shouldReceive('get')
            ->once()
            ->with(GetEventStatsAction::class, ['event_id' => 12], ['start_date' => '2026-11-01', 'end_date' => '2026-11-06'])
            ->andReturn($this->ok($this->eventStats()));

        $this->structured((new GetEventStatsTool($this->api))->handle(new Request(['event_id' => 12, 'from' => '2026-11-01'])));
    }

    public function test_event_stats_reject_a_range_that_ends_before_it_starts(): void
    {
        $this->api->shouldNotReceive('get');

        $this->expectException(ValidationException::class);

        (new GetEventStatsTool($this->api))->handle(new Request(['event_id' => 12, 'from' => '2026-11-05', 'to' => '2026-11-01']));
    }

    public function test_event_stats_report_the_api_range_limit(): void
    {
        $this->api->shouldReceive('get')->andReturn($this->failed(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY, [
            'errors' => ['start_date' => ['Date range must be less than 370 days.']],
        ]));

        $this->assertSame(
            'start_date: Date range must be less than 370 days.',
            $this->errorText((new GetEventStatsTool($this->api))->handle(new Request(['event_id' => 12, 'from' => '2024-01-01', 'to' => '2026-01-01']))),
        );
    }

    public function test_organizer_stats_separate_currencies_and_all_time_views(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOrganizerStatsAction::class, ['organizer_id' => 3], ['currency_code' => 'CAD'])
            ->andReturn($this->ok([
                'total_products_sold' => 12,
                'total_gross_sales' => 300.5,
                'total_views' => 800,
                'currency_code' => 'CAD',
                'all_organizers_currencies' => ['USD', 'CAD'],
                'daily_stats' => [['date' => '2026-10-07 15:00:00', 'products_sold' => 12]],
                'start_date' => '2026-10-07 15:00:00',
                'end_date' => '2026-11-06 15:00:00',
            ]));

        $result = $this->structured((new GetOrganizerStatsTool($this->api))->handle(new Request(['organizer_id' => 3, 'currency' => 'cad'])));

        $this->assertSame('CAD', $result['currency']);
        $this->assertSame(['USD'], $result['other_currencies']);
        $this->assertSame(['from' => '2026-10-07', 'to' => '2026-11-06'], $result['period']);
        $this->assertSame(['total_products_sold' => 12, 'total_gross_sales' => 300.5], $result['stats']);
        $this->assertSame(800, $result['all_time_page_views']);
        $this->assertArrayNotHasKey('daily', $result);
    }

    public function test_organizer_stats_pass_a_range_and_return_daily_rows_without_money_when_redacted(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOrganizerStatsAction::class, ['organizer_id' => 3], ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'])
            ->andReturn($this->ok([
                'total_products_sold' => 5,
                'total_views' => 10,
                'currency_code' => 'EUR',
                'all_organizers_currencies' => ['EUR'],
                'daily_stats' => [['date' => '2026-10-01 09:30:00', 'products_sold' => 5]],
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ]));

        $result = $this->structured((new GetOrganizerStatsTool($this->api))->handle(new Request([
            'organizer_id' => 3, 'from' => '2026-10-01', 'to' => '2026-10-31', 'include_daily' => true,
        ])));

        $this->assertSame([], $result['other_currencies']);
        $this->assertSame(['total_products_sold' => 5], $result['stats']);
        $this->assertSame([['date' => '2026-10-01', 'products_sold' => 5]], $result['daily']);
    }

    public function test_the_daily_breakdown_keeps_the_latest_days_when_too_long(): void
    {
        $days = array_map(
            static fn (int $offset): array => ['date' => Carbon::parse('2024-01-01')->addDays($offset)->toDateString().' 12:00:00', 'products_sold' => $offset],
            range(0, McpPresenter::MAX_DAILY_ROWS + 9),
        );
        $this->api->shouldReceive('get')->andReturn($this->ok([...$this->eventStats(), 'daily_stats' => $days]));

        $result = $this->structured((new GetEventStatsTool($this->api))->handle(new Request(['event_id' => 12, 'include_daily' => true])));

        $this->assertCount(McpPresenter::MAX_DAILY_ROWS, $result['daily']);
        $this->assertTrue($result['daily_truncated']);
        $this->assertSame(McpPresenter::MAX_DAILY_ROWS + 9, end($result['daily'])['products_sold']);
    }

    public function test_a_future_from_without_to_ends_on_that_day(): void
    {
        Carbon::setTestNow('2026-11-06 15:00:00');

        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOrganizerStatsAction::class, ['organizer_id' => 3], ['start_date' => '2026-12-01', 'end_date' => '2026-12-01'])
            ->andReturn($this->ok(['currency_code' => 'EUR', 'start_date' => '2026-12-01', 'end_date' => '2026-12-01']));

        $this->structured((new GetOrganizerStatsTool($this->api))->handle(new Request(['organizer_id' => 3, 'from' => '2026-12-01'])));
    }

    private function eventStats(): array
    {
        return [
            'daily_stats' => [
                ['date' => '2026-10-01 12:00:00', 'products_sold' => 15, 'total_sales_gross' => 225],
                ['date' => '2026-10-02 12:00:00', 'products_sold' => 25, 'total_sales_gross' => 375],
            ],
            'start_date' => '2026-10-01 00:00:00',
            'end_date' => '2026-10-02 23:59:59',
            'total_products_sold' => 40,
            'total_gross_sales' => 600,
            'total_views' => 90,
        ];
    }
}
