<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Events\CreateEventAction;
use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\Events\GetEventsAction;
use HiEvents\Http\Actions\Events\UpdateEventAction;
use HiEvents\Http\Actions\Events\UpdateEventStatusAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\CreateEventTool;
use HiEvents\Mcp\Tools\ListEventsTool;
use HiEvents\Mcp\Tools\PublishEventTool;
use HiEvents\Mcp\Tools\UnpublishEventTool;
use HiEvents\Mcp\Tools\UpdateEventTool;
use Laravel\Mcp\Request;

class EventToolsTest extends McpToolTestCase
{
    public function test_create_event_sends_only_known_fields_and_returns_a_summary_with_links(): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateEventAction::class, [], [
                'organizer_id' => 3,
                'title' => 'Jazz night',
                'start_date' => '2026-12-11 20:00',
            ])
            ->andReturn($this->ok($this->event(), ResponseCodes::HTTP_CREATED));

        $result = $this->structured((new CreateEventTool($this->api))->handle(new Request([
            'organizer_id' => 3,
            'title' => 'Jazz night',
            'start_date' => '2026-12-11 20:00',
            'status' => 'LIVE',
        ])));

        $this->assertSame(12, $result['event']['id']);
        $this->assertSame('2026-12-11T20:00:00-05:00', $result['event']['next_date']);
        $this->assertSame('https://app.example.test/event/12/jazz-night', $result['event']['links']['public_page']);
    }

    public function test_update_event_keeps_existing_values_and_sends_single_event_dates_in_local_time(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')
            ->once()
            ->with('PUT', UpdateEventAction::class, ['event_id' => 12], [
                'title' => 'Jazz night',
                'type' => 'SINGLE',
                'category' => 'COMEDY',
                'start_date' => '2026-12-11 20:00',
                'end_date' => '2026-12-11 23:00',
            ])
            ->andReturn($this->ok($this->event(['category' => 'COMEDY'])));

        $result = $this->structured((new UpdateEventTool($this->api))->handle(new Request(['event_id' => 12, 'category' => 'COMEDY'])));

        $this->assertSame('COMEDY', $result['event']['category']);
    }

    public function test_update_event_sends_description_only_when_given(): void
    {
        $this->api->shouldReceive('get')->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $method, string $action, array $parameters, array $body) => array_key_exists('description', $body) && $body['description'] === null)
            ->andReturn($this->ok($this->event()));

        (new UpdateEventTool($this->api))->handle(new Request(['event_id' => 12, 'description' => null]));
    }

    public function test_update_event_refuses_dates_on_a_recurring_event(): void
    {
        $this->api->shouldReceive('get')->andReturn($this->ok($this->event(['type' => 'RECURRING'])));
        $this->api->shouldNotReceive('send');

        $error = $this->errorText((new UpdateEventTool($this->api))->handle(new Request(['event_id' => 12, 'start_date' => '2027-01-01 10:00'])));

        $this->assertStringContainsString('recurring', $error);
    }

    public function test_update_event_on_a_recurring_event_sends_its_type_without_dates(): void
    {
        $this->api->shouldReceive('get')->andReturn($this->ok($this->event(['type' => 'RECURRING'])));
        $this->api->shouldReceive('send')
            ->once()
            ->with('PUT', UpdateEventAction::class, ['event_id' => 12], ['title' => 'Yoga', 'type' => 'RECURRING'])
            ->andReturn($this->ok($this->event(['type' => 'RECURRING', 'title' => 'Yoga'])));

        (new UpdateEventTool($this->api))->handle(new Request(['event_id' => 12, 'title' => 'Yoga']));
    }

    public function test_list_events_builds_filters_and_pagination(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetEventsAction::class, [], [
                'per_page' => ListEventsTool::PER_PAGE,
                'page' => 2,
                'sort_by' => 'created_at',
                'sort_direction' => 'desc',
                'query' => 'jazz',
                'eventsStatus' => 'upcoming',
                'filter_fields' => ['organizer_id' => ['eq' => 3], 'status' => ['eq' => 'LIVE']],
            ])
            ->andReturn(new ApiRouteResponseDTO(ResponseCodes::HTTP_OK, [
                'data' => [$this->event()],
                'meta' => ['current_page' => 2, 'last_page' => 3, 'total' => 51],
            ]));

        $result = $this->structured((new ListEventsTool($this->api))->handle(new Request([
            'organizer_id' => 3, 'status' => 'LIVE', 'timing' => 'upcoming', 'search' => 'jazz', 'page' => 2,
        ])));

        $this->assertSame(51, $result['total']);
        $this->assertSame(12, $result['events'][0]['id']);
    }

    public function test_publish_and_unpublish_set_the_matching_status(): void
    {
        $this->api->shouldReceive('send')->once()->with('PUT', UpdateEventStatusAction::class, ['event_id' => 12], ['status' => 'LIVE'])->andReturn($this->ok($this->event(['status' => 'LIVE'])));
        $this->api->shouldReceive('send')->once()->with('PUT', UpdateEventStatusAction::class, ['event_id' => 12], ['status' => 'DRAFT'])->andReturn($this->ok($this->event()));

        $this->assertSame('LIVE', $this->structured((new PublishEventTool($this->api))->handle(new Request(['event_id' => 12])))['event']['status']);
        $this->assertSame('DRAFT', $this->structured((new UnpublishEventTool($this->api))->handle(new Request(['event_id' => 12])))['event']['status']);
    }

    public function test_publish_surfaces_the_api_reason_when_it_is_refused(): void
    {
        $this->api->shouldReceive('send')->andReturn($this->failed(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY, [
            'message' => 'You must verify your account before you can update an event\'s status.',
        ]));

        $error = $this->errorText((new PublishEventTool($this->api))->handle(new Request(['event_id' => 12])));

        $this->assertStringContainsString('verify your account', $error);
    }

    public function test_writes_stop_after_the_per_minute_limit(): void
    {
        $this->api->shouldReceive('send')->andReturn($this->ok($this->event(['status' => 'LIVE'])));
        $tool = new PublishEventTool($this->api);

        for ($i = 0; $i < PublishEventTool::MAX_WRITES_PER_MINUTE; $i++) {
            $tool->handle(new Request(['event_id' => 12]));
        }

        $this->assertStringContainsString('Too many changes', $this->errorText($tool->handle(new Request(['event_id' => 12]))));
    }
}
