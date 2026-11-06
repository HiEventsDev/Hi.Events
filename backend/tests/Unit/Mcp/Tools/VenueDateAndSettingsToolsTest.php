<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\EventOccurrences\GenerateOccurrencesAction;
use HiEvents\Http\Actions\EventOccurrences\GetOccurrenceGenerationStatusAction;
use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\Events\UpdateEventLocationAction;
use HiEvents\Http\Actions\EventSettings\PartialEditEventSettingsAction;
use HiEvents\Http\Actions\Locations\CreateLocationAction;
use HiEvents\Http\Actions\Locations\DeleteLocationAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\GenerateEventDatesTool;
use HiEvents\Mcp\Tools\SetEventVenueTool;
use HiEvents\Mcp\Tools\UpdateEventSettingsTool;
use Illuminate\Support\Sleep;
use Laravel\Mcp\Request;
use Mockery;

class VenueDateAndSettingsToolsTest extends McpToolTestCase
{
    private const RULE = ['frequency' => 'weekly', 'days_of_week' => ['tuesday'], 'range' => ['type' => 'count', 'count' => 4]];

    public function test_set_event_venue_needs_exactly_one_choice(): void
    {
        $this->api->shouldNotReceive('get');

        $tool = new SetEventVenueTool($this->api);

        $this->assertStringContainsString('exactly one', $this->errorText($tool->handle(new Request(['event_id' => 12]))));
        $this->assertStringContainsString('exactly one', $this->errorText($tool->handle(new Request(['event_id' => 12, 'venue_id' => 3, 'online_details' => 'https://meet.example']))));
    }

    public function test_an_address_creates_a_venue_for_the_events_organizer_and_attaches_it(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateLocationAction::class, ['organizer_id' => 3], [
                'name' => 'Main hall',
                'structured_address' => ['venue_name' => 'Main hall', 'city' => 'Dublin', 'country' => 'IE'],
            ])
            ->andReturn($this->ok(['id' => 44]));
        $this->api->shouldReceive('send')
            ->once()
            ->with('PATCH', UpdateEventLocationAction::class, ['event_id' => 12], ['event_location' => ['type' => 'IN_PERSON', 'location_id' => 44]])
            ->andReturn($this->ok($this->event(['event_location' => ['type' => 'IN_PERSON', 'location_id' => 44, 'location' => ['name' => 'Main hall']]])));

        $result = $this->structured((new SetEventVenueTool($this->api))->handle(new Request([
            'event_id' => 12, 'venue_name' => 'Main hall', 'address' => ['city' => 'Dublin', 'country' => 'IE'],
        ])));

        $this->assertSame(44, $result['venue']['venue_id']);
    }

    public function test_a_new_venue_is_removed_when_it_cannot_be_attached(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')->once()->with('POST', CreateLocationAction::class, ['organizer_id' => 3], Mockery::any())->andReturn($this->ok(['id' => 44]));
        $this->api->shouldReceive('send')->once()->with('PATCH', UpdateEventLocationAction::class, ['event_id' => 12], Mockery::any())
            ->andReturn($this->failed(ResponseCodes::HTTP_FORBIDDEN, ['message' => 'This action is unauthorized.']));
        $this->api->shouldReceive('send')->once()->with('DELETE', DeleteLocationAction::class, ['organizer_id' => 3, 'location_id' => 44])->andReturn($this->ok([]));

        $this->assertSame('This action is unauthorized.', $this->errorText((new SetEventVenueTool($this->api))->handle(new Request([
            'event_id' => 12, 'venue_name' => 'Main hall', 'address' => ['city' => 'Dublin', 'country' => 'IE'],
        ]))));
    }

    public function test_online_details_and_clear_send_the_matching_payloads(): void
    {
        $this->api->shouldReceive('get')->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')->once()->with('PATCH', UpdateEventLocationAction::class, ['event_id' => 12], [
            'event_location' => ['type' => 'ONLINE', 'online_event_connection_details' => 'https://meet.example/jazz'],
        ])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')->once()->with('PATCH', UpdateEventLocationAction::class, ['event_id' => 12], [
            'clear_event_location' => true,
        ])->andReturn($this->ok($this->event()));

        $tool = new SetEventVenueTool($this->api);
        $this->structured($tool->handle(new Request(['event_id' => 12, 'online_details' => 'https://meet.example/jazz'])));
        $this->structured($tool->handle(new Request(['event_id' => 12, 'clear' => true])));
    }

    public function test_generating_dates_waits_until_the_job_finishes(): void
    {
        Sleep::fake();

        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', GenerateOccurrencesAction::class, ['event_id' => 12], ['recurrence_rule' => self::RULE])
            ->andReturn($this->jobStatus('IN_PROGRESS', ResponseCodes::HTTP_ACCEPTED));
        $this->api->shouldReceive('get')
            ->with(GetOccurrenceGenerationStatusAction::class, ['event_id' => 12], ['job_uuid' => 'job-1'])
            ->andReturn($this->jobStatus('IN_PROGRESS'), $this->jobStatus('FINISHED'));

        $result = $this->structured((new GenerateEventDatesTool($this->api))->handle(new Request(['event_id' => 12, 'recurrence_rule' => self::RULE])));

        $this->assertSame('FINISHED', $result['status']);
        Sleep::assertSleptTimes(2);
    }

    public function test_generating_dates_returns_the_job_when_it_is_still_running(): void
    {
        Sleep::fake();

        $this->api->shouldReceive('send')->andReturn($this->jobStatus('IN_PROGRESS', ResponseCodes::HTTP_ACCEPTED));
        $this->api->shouldReceive('get')->times(GenerateEventDatesTool::POLL_ATTEMPTS)->andReturn($this->jobStatus('IN_PROGRESS'));

        $result = $this->structured((new GenerateEventDatesTool($this->api))->handle(new Request(['event_id' => 12, 'recurrence_rule' => self::RULE])));

        $this->assertSame(['status' => 'IN_PROGRESS', 'job_uuid' => 'job-1', 'message' => 'Working'], $result);
    }

    public function test_a_failed_generation_is_an_error(): void
    {
        Sleep::fake();

        $this->api->shouldReceive('send')->andReturn($this->jobStatus('IN_PROGRESS', ResponseCodes::HTTP_ACCEPTED));
        $this->api->shouldReceive('get')->andReturn($this->jobStatus('FAILED'));

        $this->errorText((new GenerateEventDatesTool($this->api))->handle(new Request(['event_id' => 12, 'recurrence_rule' => self::RULE])));
    }

    public function test_settings_updates_only_send_allowed_fields(): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('PATCH', PartialEditEventSettingsAction::class, ['event_id' => 12], ['support_email' => 'help@example.com'])
            ->andReturn($this->ok(['support_email' => 'help@example.com', 'payment_providers' => ['STRIPE']]));

        $result = $this->structured((new UpdateEventSettingsTool($this->api))->handle(new Request([
            'event_id' => 12,
            'support_email' => 'help@example.com',
            'payment_providers' => ['OFFLINE'],
            'offline_payment_instructions' => 'Pay at the door',
        ])));

        $this->assertSame(['support_email' => 'help@example.com'], $result['settings']);
    }

    public function test_settings_updates_need_at_least_one_allowed_field(): void
    {
        $this->api->shouldNotReceive('send');

        $this->errorText((new UpdateEventSettingsTool($this->api))->handle(new Request(['event_id' => 12, 'payment_providers' => ['OFFLINE']])));
    }

    private function jobStatus(string $status, int $code = ResponseCodes::HTTP_OK): ApiRouteResponseDTO
    {
        return new ApiRouteResponseDTO($code, ['status' => $status, 'job_uuid' => 'job-1', 'message' => $status === 'FINISHED' ? 'Done' : 'Working']);
    }
}
