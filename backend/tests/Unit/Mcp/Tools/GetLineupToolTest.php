<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\Lineup\GetEventLineupAction;
use HiEvents\Mcp\Tools\GetLineupTool;
use Laravel\Mcp\Request;

class GetLineupToolTest extends McpToolTestCase
{
    public function test_it_flattens_performers_spaces_and_local_schedule_times(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')->once()->with(GetEventLineupAction::class, ['event_id' => 12])->andReturn($this->ok([
            'entries' => [[
                'id' => 77, 'profile_id' => 40, 'billing_tier' => 'HEADLINER', 'role_label' => 'Closing set', 'is_hidden' => false,
                'profile' => ['id' => 40, 'name' => 'The Ensemble', 'tagline' => 'Jazz', 'bio' => '<p>Long bio</p>', 'images' => []],
            ]],
            'spaces' => [['id' => 4, 'name' => 'Main stage', 'event_location_id' => null, 'venue' => null]],
            'schedule_items' => [['id' => 6, 'title' => null, 'starts_at' => '2026-12-13 01:00:00', 'ends_at' => '2026-12-13 02:30:00', 'event_space_id' => 4, 'lineup_entry_ids' => [77]]],
            'venues' => [],
        ]));

        $result = $this->structured((new GetLineupTool($this->api))->handle(new Request(['event_id' => 12])));

        $this->assertSame([
            'id' => 77, 'profile_id' => 40, 'name' => 'The Ensemble', 'tagline' => 'Jazz',
            'billing_tier' => 'HEADLINER', 'role_label' => 'Closing set', 'is_hidden' => false,
        ], $result['performers'][0]);
        $this->assertSame([['id' => 4, 'name' => 'Main stage']], $result['spaces']);
        $this->assertSame('2026-12-12T20:00:00-05:00', $result['schedule'][0]['starts_at']);
    }
}
