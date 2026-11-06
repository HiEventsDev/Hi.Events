<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\Lineup\CreateScheduleItemAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\AddScheduleItemTool;
use Laravel\Mcp\Request;

class AddScheduleItemToolTest extends McpToolTestCase
{
    public function test_it_adds_a_slot_and_returns_its_times_in_the_event_timezone(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateScheduleItemAction::class, ['event_id' => 12], [
                'starts_at' => '2026-12-12 20:00',
                'ends_at' => '2026-12-12 21:30',
                'event_space_id' => 4,
                'lineup_entry_ids' => [77],
            ])
            ->andReturn($this->ok([
                'id' => 6, 'title' => null, 'starts_at' => '2026-12-13 01:00:00', 'ends_at' => '2026-12-13 02:30:00',
                'event_space_id' => 4, 'lineup_entry_ids' => [77],
            ], ResponseCodes::HTTP_CREATED));

        $result = $this->structured((new AddScheduleItemTool($this->api))->handle(new Request([
            'event_id' => 12, 'starts_at' => '2026-12-12 20:00', 'ends_at' => '2026-12-12 21:30', 'event_space_id' => 4, 'lineup_entry_ids' => [77],
        ])));

        $this->assertSame([
            'id' => 6,
            'title' => null,
            'starts_at' => '2026-12-12T20:00:00-05:00',
            'ends_at' => '2026-12-12T21:30:00-05:00',
            'space_id' => 4,
            'lineup_entry_ids' => [77],
        ], $result['schedule_item']);
    }

    public function test_a_slot_with_neither_title_nor_performers_reports_the_api_reason(): void
    {
        $this->api->shouldReceive('get')->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')->andReturn($this->failed(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY, [
            'errors' => ['title' => ['Give the slot a title or add at least one performer.']],
        ]));

        $this->assertStringContainsString('title:', $this->errorText((new AddScheduleItemTool($this->api))->handle(new Request([
            'event_id' => 12, 'starts_at' => '2026-12-12 20:00', 'ends_at' => '2026-12-12 21:30',
        ]))));
    }
}
