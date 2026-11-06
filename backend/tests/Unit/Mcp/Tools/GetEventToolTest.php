<?php

namespace Tests\Unit\Mcp\Tools;

use Carbon\CarbonImmutable;
use HiEvents\Http\Actions\EventOccurrences\GetEventOccurrencesAction;
use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\Products\GetProductsAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\GetEventTool;
use Laravel\Mcp\Request;

class GetEventToolTest extends McpToolTestCase
{
    public function test_it_combines_the_event_its_upcoming_dates_and_its_tickets(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00'));

        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event([
            'description' => '<p>Live jazz.</p>',
            'event_location' => ['type' => 'ONLINE', 'location_id' => null, 'online_event_connection_details' => 'https://meet.example/jazz'],
        ])));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetEventOccurrencesAction::class, ['event_id' => 12], [
                'per_page' => GetEventTool::MAX_DATES,
                'filter_fields' => ['start_date' => ['gte' => '2026-11-10 12:00:00']],
            ])
            ->andReturn(new ApiRouteResponseDTO(ResponseCodes::HTTP_OK, [
                'data' => [['id' => 31, 'start_date' => '2026-12-12 01:00:00', 'end_date' => null, 'status' => 'ACTIVE']],
                'meta' => ['total' => 1],
            ]));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetProductsAction::class, ['event_id' => 12], ['per_page' => GetEventTool::MAX_TICKETS])
            ->andReturn($this->ok([['id' => 5, 'title' => 'GA', 'type' => 'FREE', 'product_type' => 'TICKET', 'prices' => []]]));

        $result = $this->structured((new GetEventTool($this->api))->handle(new Request(['event_id' => 12])));

        $this->assertSame('<p>Live jazz.</p>', $result['event']['description']);
        $this->assertSame('ONLINE', $result['event']['venue']['type']);
        $this->assertSame('https://app.example.test/manage/event/12/dashboard', $result['event']['links']['dashboard']);
        $this->assertSame('2026-12-11T20:00:00-05:00', $result['upcoming_dates'][0]['start_date']);
        $this->assertSame(1, $result['upcoming_dates_total']);
        $this->assertSame('GA', $result['tickets'][0]['title']);
    }

    public function test_an_event_in_another_account_stops_before_any_other_request(): void
    {
        $this->api->shouldReceive('get')->once()->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->failed(ResponseCodes::HTTP_FORBIDDEN, [
            'message' => 'This action is unauthorized',
        ]));

        $this->assertSame('This action is unauthorized', $this->errorText((new GetEventTool($this->api))->handle(new Request(['event_id' => 12]))));
    }
}
