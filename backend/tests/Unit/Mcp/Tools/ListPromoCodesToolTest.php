<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\PromoCodes\GetPromoCodesAction;
use HiEvents\Mcp\Tools\ListPromoCodesTool;
use Laravel\Mcp\Request;

class ListPromoCodesToolTest extends McpToolTestCase
{
    public function test_it_lists_codes_with_expiry_in_the_event_timezone(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetPromoCodesAction::class, ['event_id' => 12], ['per_page' => ListPromoCodesTool::PER_PAGE])
            ->andReturn($this->ok([[
                'id' => 4, 'code' => 'early20', 'discount_type' => 'PERCENTAGE', 'discount' => 20, 'discount_applies_to' => 'EACH_PRODUCT',
                'applicable_product_ids' => [5], 'expiry_date' => '2027-06-01 04:00:00', 'max_allowed_usages' => 100, 'order_usage_count' => 12,
                'attendee_usage_count' => 30, 'created_at' => '2026-11-01 10:00:00',
            ]]));

        $result = $this->structured((new ListPromoCodesTool($this->api))->handle(new Request(['event_id' => 12])));

        $this->assertSame('2027-06-01T00:00:00-04:00', $result['promo_codes'][0]['expiry_date']);
        $this->assertSame(12, $result['promo_codes'][0]['order_usage_count']);
        $this->assertArrayNotHasKey('created_at', $result['promo_codes'][0]);
    }
}
