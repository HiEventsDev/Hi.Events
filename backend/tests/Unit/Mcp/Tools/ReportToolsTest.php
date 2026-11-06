<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\Organizers\GetOrganizerAction;
use HiEvents\Http\Actions\Reports\GetOrganizerReportAction;
use HiEvents\Http\Actions\Reports\GetReportAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\GetEventReportTool;
use HiEvents\Mcp\Tools\GetOrganizerReportTool;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Mockery;

class ReportToolsTest extends McpToolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-06 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_an_event_report_sends_the_event_timezone_day_bounds_in_utc(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetReportAction::class, ['event_id' => 12, 'report_type' => 'product_sales'], [
                'start_date' => '2026-10-01T04:00:00+00:00',
                'end_date' => '2026-10-02T03:59:59+00:00',
                'occurrence_id' => 7,
            ])
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: [['product_id' => 1, 'number_sold' => 4]]));

        $result = $this->structured((new GetEventReportTool($this->api))->handle(new Request([
            'event_id' => 12, 'report_type' => 'product_sales', 'from' => '2026-10-01', 'to' => '2026-10-01', 'date_id' => 7,
        ])));

        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-01'], $result['period']);
        $this->assertSame('USD', $result['currency']);
        $this->assertSame([['product_id' => 1, 'number_sold' => 4]], $result['rows']);
        $this->assertSame(1, $result['total_rows']);
        $this->assertFalse($result['truncated']);
    }

    public function test_an_event_report_defaults_to_the_last_30_days_and_localizes_timestamps(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetReportAction::class, ['event_id' => 12, 'report_type' => 'promo_codes_report'], [
                'start_date' => '2026-10-07T04:00:00+00:00',
                'end_date' => '2026-11-07T04:59:59+00:00',
            ])
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: [
                ['promo_code' => 'EARLY', 'first_used_at' => '2026-10-10 18:00:00', 'expiry_date' => null],
            ]));

        $result = $this->structured((new GetEventReportTool($this->api))->handle(new Request(['event_id' => 12, 'report_type' => 'promo_codes_report'])));

        $this->assertSame(['from' => '2026-10-07', 'to' => '2026-11-06'], $result['period']);
        $this->assertSame([['promo_code' => 'EARLY', 'first_used_at' => '2026-10-10T14:00:00-04:00', 'expiry_date' => null]], $result['rows']);
    }

    public function test_a_long_event_report_is_cut_at_the_row_limit(): void
    {
        $rows = array_map(static fn (int $id): array => ['occurrence_id' => $id], range(1, GetEventReportTool::MAX_ROWS + 5));
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')->with(GetReportAction::class, Mockery::any(), Mockery::any())
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: $rows));

        $result = $this->structured((new GetEventReportTool($this->api))->handle(new Request(['event_id' => 12, 'report_type' => 'occurrence_summary'])));

        $this->assertCount(GetEventReportTool::MAX_ROWS, $result['rows']);
        $this->assertSame(GetEventReportTool::MAX_ROWS + 5, $result['total_rows']);
        $this->assertTrue($result['truncated']);
    }

    public function test_an_event_report_without_financial_access_returns_the_api_refusal(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')->with(GetReportAction::class, Mockery::any(), Mockery::any())
            ->andReturn($this->failed(ResponseCodes::HTTP_FORBIDDEN, ['message' => 'You do not have permission to perform this action.']));

        $this->assertSame(
            'You do not have permission to perform this action.',
            $this->errorText((new GetEventReportTool($this->api))->handle(new Request(['event_id' => 12, 'report_type' => 'daily_sales_report']))),
        );
    }

    public function test_an_unknown_report_type_is_rejected_before_any_call(): void
    {
        $this->api->shouldNotReceive('get');

        $this->expectException(ValidationException::class);

        (new GetEventReportTool($this->api))->handle(new Request(['event_id' => 12, 'report_type' => 'attendees']));
    }

    public function test_a_ranged_organizer_report_defaults_to_the_organizer_currency(): void
    {
        $this->api->shouldReceive('get')->with(GetOrganizerAction::class, ['organizer_id' => 3])->andReturn($this->ok($this->organizer()));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOrganizerReportAction::class, ['organizer_id' => 3, 'report_type' => 'revenue_summary'], [
                'start_date' => '2026-09-30T23:00:00+00:00',
                'end_date' => '2026-10-31T23:59:59+00:00',
                'currency' => 'EUR',
            ])
            ->andReturn($this->ok([['date' => '2026-10-01', 'gross_sales' => 100]]));

        $result = $this->structured((new GetOrganizerReportTool($this->api))->handle(new Request([
            'organizer_id' => 3, 'report_type' => 'revenue_summary', 'from' => '2026-10-01', 'to' => '2026-10-31',
        ])));

        $this->assertSame('EUR', $result['currency']);
        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-31'], $result['period']);
        $this->assertSame([['date' => '2026-10-01', 'gross_sales' => 100]], $result['rows']);
        $this->assertArrayNotHasKey('page', $result);
    }

    public function test_an_all_time_organizer_report_sends_no_range_and_localizes_event_dates(): void
    {
        $this->api->shouldReceive('get')->with(GetOrganizerAction::class, ['organizer_id' => 3])->andReturn($this->ok($this->organizer()));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOrganizerReportAction::class, ['organizer_id' => 3, 'report_type' => 'events_performance'], ['currency' => 'EUR'])
            ->andReturn($this->ok([['event_id' => 12, 'start_date' => '2026-12-12 01:00:00', 'gross_revenue' => 100]]));

        $result = $this->structured((new GetOrganizerReportTool($this->api))->handle(new Request(['organizer_id' => 3, 'report_type' => 'events_performance'])));

        $this->assertSame('all_time', $result['period']);
        $this->assertSame([['event_id' => 12, 'start_date' => '2026-12-12T01:00:00+00:00', 'gross_revenue' => 100]], $result['rows']);
    }

    public function test_check_in_summary_covers_every_currency(): void
    {
        $this->api->shouldReceive('get')->with(GetOrganizerAction::class, ['organizer_id' => 3])->andReturn($this->ok($this->organizer()));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOrganizerReportAction::class, ['organizer_id' => 3, 'report_type' => 'check_in_summary'], [])
            ->andReturn($this->ok([['event_id' => 12, 'total_checked_in' => 4]]));

        $result = $this->structured((new GetOrganizerReportTool($this->api))->handle(new Request(['organizer_id' => 3, 'report_type' => 'check_in_summary'])));

        $this->assertArrayNotHasKey('currency', $result);
    }

    public function test_all_time_reports_refuse_a_date_range(): void
    {
        $this->api->shouldNotReceive('get');

        $this->assertSame('This report covers all time and takes no date range.', $this->errorText((new GetOrganizerReportTool($this->api))->handle(new Request([
            'organizer_id' => 3, 'report_type' => 'events_performance', 'from' => '2026-10-01',
        ]))));
        $this->assertSame('This report covers all time and takes no date range.', $this->errorText((new GetEventReportTool($this->api))->handle(new Request([
            'event_id' => 12, 'report_type' => 'seating_sales', 'from' => '2026-10-01',
        ]))));
    }

    public function test_occurrence_summary_refuses_a_date_id_and_localizes_its_dates(): void
    {
        $this->assertStringContainsString('leave out date_id', $this->errorText((new GetEventReportTool($this->api))->handle(new Request([
            'event_id' => 12, 'report_type' => 'occurrence_summary', 'date_id' => 7,
        ]))));

        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetReportAction::class, ['event_id' => 12, 'report_type' => 'occurrence_summary'], [])
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: [
                ['occurrence_id' => 7, 'start_date' => '2026-12-13 01:00:00', 'end_date' => null],
            ]));

        $result = $this->structured((new GetEventReportTool($this->api))->handle(new Request(['event_id' => 12, 'report_type' => 'occurrence_summary'])));

        $this->assertSame('all_time', $result['period']);
        $this->assertSame([['occurrence_id' => 7, 'start_date' => '2026-12-12T20:00:00-05:00', 'end_date' => null]], $result['rows']);
    }

    public function test_a_future_from_without_to_ends_on_that_day(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetReportAction::class, ['event_id' => 12, 'report_type' => 'daily_sales_report'], [
                'start_date' => '2026-12-01T05:00:00+00:00',
                'end_date' => '2026-12-02T04:59:59+00:00',
            ])
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: []));

        $result = $this->structured((new GetEventReportTool($this->api))->handle(new Request([
            'event_id' => 12, 'report_type' => 'daily_sales_report', 'from' => '2026-12-01',
        ])));

        $this->assertSame(['from' => '2026-12-01', 'to' => '2026-12-01'], $result['period']);
    }

    public function test_platform_fees_are_paged(): void
    {
        $this->api->shouldReceive('get')->with(GetOrganizerAction::class, ['organizer_id' => 3])->andReturn($this->ok($this->organizer()));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOrganizerReportAction::class, ['organizer_id' => 3, 'report_type' => 'platform_fees'], Mockery::on(
                static fn (array $query): bool => $query['currency'] === 'USD' && $query['page'] === 2 && $query['per_page'] === GetOrganizerReportTool::PLATFORM_FEES_PER_PAGE,
            ))
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: [
                'data' => [['order_id' => 5, 'payment_date' => '2026-10-10 18:00:00']],
                'pagination' => ['total' => 101, 'page' => 2, 'per_page' => 100, 'last_page' => 2],
            ]));

        $result = $this->structured((new GetOrganizerReportTool($this->api))->handle(new Request([
            'organizer_id' => 3, 'report_type' => 'platform_fees', 'currency' => 'usd', 'page' => 2,
        ])));

        $this->assertSame([['order_id' => 5, 'payment_date' => '2026-10-10T19:00:00+01:00']], $result['rows']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(2, $result['last_page']);
        $this->assertSame(101, $result['total']);
        $this->assertArrayNotHasKey('total_rows', $result);
        $this->assertArrayNotHasKey('truncated', $result);
    }

    public function test_an_organizer_report_for_another_account_is_not_found(): void
    {
        $this->api->shouldReceive('get')->with(GetOrganizerAction::class, ['organizer_id' => 3])
            ->andReturn($this->failed(ResponseCodes::HTTP_NOT_FOUND, []));
        $this->api->shouldNotReceive('get')->with(GetOrganizerReportAction::class, Mockery::any(), Mockery::any());

        $this->assertSame('Not found in this account.', $this->errorText((new GetOrganizerReportTool($this->api))->handle(new Request([
            'organizer_id' => 3, 'report_type' => 'revenue_summary',
        ]))));
    }

    private function organizer(): array
    {
        return ['id' => 3, 'name' => 'Jazz Club', 'timezone' => 'Europe/Dublin', 'currency' => 'EUR'];
    }
}
