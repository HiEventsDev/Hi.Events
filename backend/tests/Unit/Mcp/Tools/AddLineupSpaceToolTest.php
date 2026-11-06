<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Lineup\CreateEventSpaceAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\AddLineupSpaceTool;
use Laravel\Mcp\Request;

class AddLineupSpaceToolTest extends McpToolTestCase
{
    public function test_it_creates_a_space_with_only_its_name(): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateEventSpaceAction::class, ['event_id' => 12], ['name' => 'Main stage'])
            ->andReturn($this->ok(['id' => 4, 'name' => 'Main stage', 'event_location_id' => null], ResponseCodes::HTTP_CREATED));

        $result = $this->structured((new AddLineupSpaceTool($this->api))->handle(new Request([
            'event_id' => 12, 'name' => 'Main stage', 'event_location_id' => 3,
        ])));

        $this->assertSame(['id' => 4, 'name' => 'Main stage'], $result['space']);
    }
}
