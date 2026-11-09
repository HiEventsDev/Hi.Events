<?php

namespace Tests\Unit\Mcp\Tools\Support;

use HiEvents\Mcp\Tools\Support\TicketPayload;
use Tests\TestCase;

class TicketPayloadTest extends TestCase
{
    public function test_an_existing_product_becomes_a_full_edit_payload(): void
    {
        $payload = TicketPayload::fromExistingProduct([
            'id' => 5,
            'title' => 'GA',
            'type' => 'PAID',
            'product_type' => 'TICKET',
            'product_category_id' => 2,
            'hide_when_sold_out' => true,
            'is_hidden_without_promo_code' => true,
            'sale_start_date' => '2026-12-01 14:00:00',
            'sale_end_date' => null,
            'quantity_sold' => 40,
            'is_available' => true,
            'taxes_and_fees' => [['id' => 7, 'name' => 'VAT'], ['id' => 8, 'name' => 'Booking fee']],
            'addon_product_ids' => [11],
            'prices' => [[
                'id' => 9,
                'label' => null,
                'price' => 15,
                'initial_quantity_available' => 100,
                'sale_start_date' => null,
                'sale_end_date' => '2026-12-12 00:00:00',
                'is_hidden' => false,
                'quantity_applies_to' => 'EVENT',
                'is_sold_out' => false,
            ]],
        ], 'America/New_York');

        $this->assertSame('GA', $payload['title']);
        $this->assertSame(2, $payload['product_category_id']);
        $this->assertTrue($payload['hide_when_sold_out']);
        $this->assertTrue($payload['is_hidden_without_promo_code']);
        $this->assertSame('2026-12-01 09:00', $payload['sale_start_date']);
        $this->assertNull($payload['sale_end_date']);
        $this->assertSame([7, 8], $payload['tax_and_fee_ids']);
        $this->assertSame([11], $payload['addon_product_ids']);
        $this->assertArrayNotHasKey('quantity_sold', $payload);
        $this->assertArrayNotHasKey('is_available', $payload);
        $this->assertSame([
            'id' => 9,
            'label' => null,
            'price' => 15,
            'initial_quantity_available' => 100,
            'sale_start_date' => null,
            'sale_end_date' => '2026-12-11 19:00',
            'is_hidden' => false,
            'quantity_applies_to' => 'EVENT',
        ], $payload['prices'][0]);
    }

    public function test_a_product_without_taxes_or_addons_sends_empty_lists(): void
    {
        $payload = TicketPayload::fromExistingProduct(['title' => 'Free', 'type' => 'FREE', 'prices' => []], 'UTC');

        $this->assertSame([], $payload['tax_and_fee_ids']);
        $this->assertSame([], $payload['addon_product_ids']);
    }

    public function test_tiers_map_to_prices_and_omit_unset_fields(): void
    {
        $this->assertSame([
            ['id' => 3, 'label' => 'Early bird', 'price' => 10, 'initial_quantity_available' => 30],
            ['label' => 'Standard', 'price' => 18, 'is_hidden' => false],
        ], TicketPayload::tierPrices([
            ['id' => 3, 'label' => 'Early bird', 'price' => 10, 'quantity_available' => 30],
            ['label' => 'Standard', 'price' => 18, 'is_hidden' => false],
        ]));
    }
}
