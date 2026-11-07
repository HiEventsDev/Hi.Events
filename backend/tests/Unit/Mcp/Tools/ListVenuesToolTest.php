<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Locations\GetLocationsAction;
use HiEvents\Mcp\Tools\ListVenuesTool;
use Laravel\Mcp\Request;

class ListVenuesToolTest extends McpToolTestCase
{
    public function test_it_lists_the_organizers_venues_without_provider_details(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetLocationsAction::class, ['organizer_id' => 3], ['per_page' => ListVenuesTool::PER_PAGE])
            ->andReturn($this->ok([[
                'id' => 8, 'organizer_id' => 3, 'name' => 'Main hall', 'structured_address' => ['city' => 'Dublin'],
                'latitude' => 53.3, 'longitude' => -6.2, 'provider' => 'google', 'provider_place_id' => 'abc', 'created_at' => '2026-01-01',
            ]]));

        $this->assertSame(
            [['id' => 8, 'name' => 'Main hall', 'address' => ['city' => 'Dublin']]],
            $this->structured((new ListVenuesTool($this->api))->handle(new Request(['organizer_id' => 3])))['venues'],
        );
    }
}
