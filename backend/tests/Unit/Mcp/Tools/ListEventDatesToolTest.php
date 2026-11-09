<?php

namespace Tests\Unit\Mcp\Tools;

use Carbon\CarbonImmutable;
use HiEvents\Http\Actions\EventOccurrences\GetEventOccurrencesAction;
use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\ListEventDatesTool;
use Laravel\Mcp\Request;

class ListEventDatesToolTest extends McpToolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00'));
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
    }

    public function test_upcoming_dates_are_listed_by_default_in_the_event_timezone(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetEventOccurrencesAction::class, ['event_id' => 12], [
                'per_page' => ListEventDatesTool::PER_PAGE,
                'page' => 1,
                'filter_fields' => ['start_date' => ['gte' => '2026-11-10 12:00:00']],
            ])
            ->andReturn($this->dates());

        $result = $this->structured((new ListEventDatesTool($this->api))->handle(new Request(['event_id' => 12])));

        $this->assertSame('2026-12-11T20:00:00-05:00', $result['dates'][0]['start_date']);
        $this->assertSame(['page' => 2, 'last_page' => 3, 'total' => 120], array_intersect_key($result, array_flip(['page', 'last_page', 'total'])));
    }

    public function test_past_dates_are_included_on_request(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetEventOccurrencesAction::class, ['event_id' => 12], ['per_page' => ListEventDatesTool::PER_PAGE, 'page' => 2])
            ->andReturn($this->dates());

        $this->structured((new ListEventDatesTool($this->api))->handle(new Request(['event_id' => 12, 'include_past' => true, 'page' => 2])));
    }

    private function dates(): ApiRouteResponseDTO
    {
        return new ApiRouteResponseDTO(ResponseCodes::HTTP_OK, [
            'data' => [['id' => 31, 'start_date' => '2026-12-12 01:00:00', 'end_date' => '2026-12-12 04:00:00', 'status' => 'ACTIVE']],
            'meta' => ['current_page' => 2, 'last_page' => 3, 'total' => 120],
        ]);
    }
}
