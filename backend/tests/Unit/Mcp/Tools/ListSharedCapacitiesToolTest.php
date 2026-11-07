<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\CapacityAssignments\GetCapacityAssignmentsAction;
use HiEvents\Mcp\Tools\ListSharedCapacitiesTool;
use Laravel\Mcp\Request;

class ListSharedCapacitiesToolTest extends McpToolTestCase
{
    public function test_it_lists_capacities_with_usage_and_tickets(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetCapacityAssignmentsAction::class, ['event_id' => 12], ['per_page' => ListSharedCapacitiesTool::PER_PAGE])
            ->andReturn($this->ok([[
                'id' => 3, 'name' => 'Main hall', 'capacity' => 200, 'used_capacity' => 150, 'percentage_used' => 75,
                'applies_to' => 'PRODUCTS', 'status' => 'ACTIVE', 'event_id' => 12, 'products' => [['id' => 5, 'title' => 'Standard']],
            ]]));

        $this->assertSame([[
            'id' => 3, 'name' => 'Main hall', 'capacity' => 200, 'used_capacity' => 150, 'status' => 'ACTIVE',
            'tickets' => [['id' => 5, 'title' => 'Standard']],
        ]], $this->structured((new ListSharedCapacitiesTool($this->api))->handle(new Request(['event_id' => 12])))['shared_capacities']);
    }
}
