<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Widgets\GetEventWidgetsAction;
use HiEvents\Http\Actions\Widgets\GetOrganizerWidgetsAction;
use HiEvents\Mcp\Tools\ListWidgetsTool;
use Laravel\Mcp\Request;

class ListWidgetsToolTest extends McpToolTestCase
{
    public function test_event_widgets_come_with_their_embed_code(): void
    {
        $this->api->shouldReceive('get')->once()->with(GetEventWidgetsAction::class, ['event_id' => 12])->andReturn($this->ok([
            ['id' => 1, 'name' => 'Tickets', 'type' => 'TICKETS', 'short_id' => 'wgt_1', 'event_id' => 12, 'organizer_id' => 3, 'settings' => []],
        ]));

        $widget = $this->structured((new ListWidgetsTool($this->api))->handle(new Request(['event_id' => 12])))['widgets'][0];

        $this->assertSame('wgt_1', $widget['short_id']);
        $this->assertStringContainsString('data-hievents-widget="wgt_1"', $widget['embed_code']);
    }

    public function test_organizer_widgets_are_listed_by_organizer(): void
    {
        $this->api->shouldReceive('get')->once()->with(GetOrganizerWidgetsAction::class, ['organizer_id' => 3])->andReturn($this->ok([
            ['id' => 2, 'name' => 'All events', 'type' => 'EVENTS', 'short_id' => 'wgt_2', 'event_id' => null, 'organizer_id' => 3, 'settings' => []],
        ]));

        $widget = $this->structured((new ListWidgetsTool($this->api))->handle(new Request(['organizer_id' => 3])))['widgets'][0];

        $this->assertStringContainsString('data-hievents-organizer-id="3"', $widget['embed_code']);
    }

    public function test_an_event_or_organizer_is_required(): void
    {
        $this->api->shouldNotReceive('get');

        $this->errorText((new ListWidgetsTool($this->api))->handle(new Request([])));
    }
}
