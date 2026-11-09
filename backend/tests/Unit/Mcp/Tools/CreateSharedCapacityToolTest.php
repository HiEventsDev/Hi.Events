<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\CapacityAssignments\CreateCapacityAssignmentAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\CreateSharedCapacityTool;
use Laravel\Mcp\Request;

class CreateSharedCapacityToolTest extends McpToolTestCase
{
    public function test_it_creates_an_active_capacity_for_the_given_tickets(): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateCapacityAssignmentAction::class, ['event_id' => 12], [
                'status' => 'ACTIVE',
                'name' => 'Main hall',
                'capacity' => 200,
                'product_ids' => [5, 6],
            ])
            ->andReturn($this->ok([
                'id' => 3, 'name' => 'Main hall', 'capacity' => 200, 'used_capacity' => 0, 'status' => 'ACTIVE',
                'products' => [['id' => 5, 'title' => 'Standard'], ['id' => 6, 'title' => 'VIP']],
            ], ResponseCodes::HTTP_CREATED));

        $result = $this->structured((new CreateSharedCapacityTool($this->api))->handle(new Request([
            'event_id' => 12, 'name' => 'Main hall', 'capacity' => 200, 'product_ids' => [5, 6],
        ])));

        $this->assertSame([['id' => 5, 'title' => 'Standard'], ['id' => 6, 'title' => 'VIP']], $result['shared_capacity']['tickets']);
    }

    public function test_tickets_from_another_event_are_refused_with_the_api_message(): void
    {
        $this->api->shouldReceive('send')->andReturn($this->failed(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY, [
            'errors' => ['product_ids' => ['Invalid product ids: 99']],
        ]));

        $this->assertSame('product_ids: Invalid product ids: 99', $this->errorText((new CreateSharedCapacityTool($this->api))->handle(new Request([
            'event_id' => 12, 'name' => 'Main hall', 'capacity' => 200, 'product_ids' => [99],
        ]))));
    }
}
