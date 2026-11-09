<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\Products\GetProductsAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\ListTicketsTool;
use Laravel\Mcp\Request;

class ListTicketsToolTest extends McpToolTestCase
{
    public function test_it_lists_tickets_with_local_sale_dates(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetProductsAction::class, ['event_id' => 12], ['per_page' => ListTicketsTool::PER_PAGE])
            ->andReturn($this->ok([[
                'id' => 5, 'title' => 'Early bird', 'type' => 'PAID', 'product_type' => 'TICKET', 'sale_end_date' => '2026-12-01 05:00:00',
                'prices' => [['id' => 9, 'price' => 15, 'initial_quantity_available' => 50, 'quantity_sold' => 20]],
            ]]));

        $ticket = $this->structured((new ListTicketsTool($this->api))->handle(new Request(['event_id' => 12])))['tickets'][0];

        $this->assertSame('2026-12-01T00:00:00-05:00', $ticket['sale_end_date']);
        $this->assertSame(20, $ticket['prices'][0]['quantity_sold']);
    }

    public function test_an_unknown_event_is_reported_without_listing_tickets(): void
    {
        $this->api->shouldReceive('get')->once()->andReturn($this->failed(ResponseCodes::HTTP_NOT_FOUND, []));

        $this->assertSame('Not found in this account.', $this->errorText((new ListTicketsTool($this->api))->handle(new Request(['event_id' => 99]))));
    }
}
