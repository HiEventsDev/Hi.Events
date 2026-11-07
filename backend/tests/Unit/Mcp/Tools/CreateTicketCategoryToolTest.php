<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\ProductCategories\CreateProductCategoryAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\CreateTicketCategoryTool;
use Laravel\Mcp\Request;

class CreateTicketCategoryToolTest extends McpToolTestCase
{
    public function test_a_category_is_visible_unless_asked_otherwise(): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateProductCategoryAction::class, ['event_id' => 12], ['is_hidden' => false, 'name' => 'Workshops'])
            ->andReturn($this->ok(['id' => 8, 'name' => 'Workshops', 'description' => null, 'is_hidden' => false, 'order' => 2], ResponseCodes::HTTP_CREATED));

        $result = $this->structured((new CreateTicketCategoryTool($this->api))->handle(new Request(['event_id' => 12, 'name' => 'Workshops'])));

        $this->assertSame(['id' => 8, 'name' => 'Workshops', 'description' => null, 'is_hidden' => false], $result['category']);
    }

    public function test_a_hidden_category_keeps_its_choice(): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateProductCategoryAction::class, ['event_id' => 12], ['is_hidden' => true, 'name' => 'Comps', 'no_products_message' => 'Ask at the door'])
            ->andReturn($this->ok(['id' => 9, 'name' => 'Comps', 'is_hidden' => true]));

        $this->assertTrue($this->structured((new CreateTicketCategoryTool($this->api))->handle(new Request([
            'event_id' => 12, 'name' => 'Comps', 'is_hidden' => true, 'no_products_message' => 'Ask at the door',
        ])))['category']['is_hidden']);
    }
}
