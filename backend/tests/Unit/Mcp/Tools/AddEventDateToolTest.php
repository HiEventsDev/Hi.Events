<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\EventOccurrences\CreateEventOccurrenceAction;
use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\AddEventDateTool;
use Laravel\Mcp\Request;

class AddEventDateToolTest extends McpToolTestCase
{
    public function test_it_adds_a_date_and_returns_it_in_the_event_timezone(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateEventOccurrenceAction::class, ['event_id' => 12], [
                'start_date' => '2026-12-12 20:00',
                'end_date' => '2026-12-12 23:00',
                'capacity' => 150,
                'label' => 'Encore',
            ])
            ->andReturn($this->ok([
                'id' => 31, 'start_date' => '2026-12-13 01:00:00', 'end_date' => '2026-12-13 04:00:00',
                'status' => 'ACTIVE', 'label' => 'Encore', 'capacity' => 150, 'available_capacity' => 150,
            ], ResponseCodes::HTTP_CREATED));

        $result = $this->structured((new AddEventDateTool($this->api))->handle(new Request([
            'event_id' => 12, 'start_date' => '2026-12-12 20:00', 'end_date' => '2026-12-12 23:00', 'capacity' => 150, 'label' => 'Encore',
        ])));

        $this->assertSame('2026-12-12T20:00:00-05:00', $result['date']['start_date']);
        $this->assertSame('Encore', $result['date']['label']);
    }

    public function test_an_event_outside_the_account_is_not_found_and_nothing_is_created(): void
    {
        $this->api->shouldReceive('get')->andReturn($this->failed(ResponseCodes::HTTP_NOT_FOUND, []));
        $this->api->shouldNotReceive('send');

        $this->assertSame('Not found in this account.', $this->errorText((new AddEventDateTool($this->api))->handle(new Request([
            'event_id' => 99, 'start_date' => '2026-12-12 20:00',
        ]))));
    }
}
