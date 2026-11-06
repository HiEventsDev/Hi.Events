<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\ProductCategories\GetProductCategoriesAction;
use HiEvents\Mcp\Tools\ListTicketCategoriesTool;
use Laravel\Mcp\Request;

class ListTicketCategoriesToolTest extends McpToolTestCase
{
    public function test_it_lists_categories_with_just_their_tickets_ids_and_titles(): void
    {
        $this->api->shouldReceive('get')->once()->with(GetProductCategoriesAction::class, ['event_id' => 12])->andReturn($this->ok([
            ['id' => 2, 'name' => 'Tickets', 'description' => null, 'is_hidden' => false, 'order' => 1, 'products' => [
                ['id' => 5, 'title' => 'GA', 'prices' => [['id' => 9, 'price' => 15]], 'taxes_and_fees' => []],
            ]],
            ['id' => 3, 'name' => 'Comps', 'is_hidden' => true],
        ]));

        $this->assertSame([
            ['id' => 2, 'name' => 'Tickets', 'description' => null, 'is_hidden' => false, 'tickets' => [['id' => 5, 'title' => 'GA']]],
            ['id' => 3, 'name' => 'Comps', 'description' => null, 'is_hidden' => true, 'tickets' => []],
        ], $this->structured((new ListTicketCategoriesTool($this->api))->handle(new Request(['event_id' => 12])))['categories']);
    }
}
