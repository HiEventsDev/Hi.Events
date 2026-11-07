<?php

namespace Tests\Unit\Mcp\Tools\Support;

use HiEvents\Mcp\Tools\Support\McpPresenter;
use Tests\TestCase;

class McpPresenterTest extends TestCase
{
    public function test_local_dates_carry_the_event_timezone_offset(): void
    {
        $this->assertSame('2026-12-11T20:00:00-05:00', McpPresenter::localDate('2026-12-12 01:00:00', 'America/New_York'));
        $this->assertSame('2026-07-01T10:00:00+01:00', McpPresenter::localDate('2026-07-01T09:00:00.000000Z', 'Europe/Dublin'));
        $this->assertNull(McpPresenter::localDate(null, 'UTC'));
    }

    public function test_local_input_dates_use_the_format_tools_accept(): void
    {
        $this->assertSame('2026-12-11 20:00', McpPresenter::localInputDate('2026-12-12 01:00:00', 'America/New_York'));
        $this->assertNull(McpPresenter::localInputDate('', 'UTC'));
    }

    public function test_event_links_point_at_the_frontend(): void
    {
        config(['app.frontend_url' => 'https://app.example.test/']);

        $this->assertSame([
            'dashboard' => 'https://app.example.test/manage/event/12/dashboard',
            'public_page' => 'https://app.example.test/event/12/jazz-night',
            'preview' => 'https://app.example.test/event/12/preview',
        ], McpPresenter::eventLinks(['id' => 12, 'slug' => 'jazz-night']));
    }

    public function test_venue_flattens_the_event_location(): void
    {
        $this->assertNull(McpPresenter::venue(null));

        $this->assertSame([
            'type' => 'IN_PERSON',
            'venue_id' => 8,
            'name' => 'Main hall',
            'address' => ['city' => 'Dublin'],
            'online_details' => null,
        ], McpPresenter::venue([
            'id' => 38,
            'type' => 'IN_PERSON',
            'location_id' => 8,
            'online_event_connection_details' => null,
            'location' => ['id' => 8, 'name' => 'Main hall', 'structured_address' => ['city' => 'Dublin'], 'created_at' => '2026-01-01'],
        ]));
    }

    public function test_ticket_dates_are_local_and_internal_fields_are_dropped(): void
    {
        $ticket = McpPresenter::ticket([
            'id' => 5,
            'title' => 'GA',
            'type' => 'PAID',
            'product_type' => 'TICKET',
            'sale_end_date' => '2026-12-12 00:00:00',
            'order' => 3,
            'prices' => [['id' => 9, 'label' => null, 'price' => 15, 'initial_quantity_available' => 100, 'quantity_sold' => 4, 'is_hidden' => false]],
        ], 'America/New_York');

        $this->assertSame('2026-12-11T19:00:00-05:00', $ticket['sale_end_date']);
        $this->assertArrayNotHasKey('order', $ticket);
        $this->assertSame(100, $ticket['prices'][0]['quantity_available']);
    }
}
