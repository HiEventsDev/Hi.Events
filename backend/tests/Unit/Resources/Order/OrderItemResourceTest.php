<?php

namespace Tests\Unit\Resources\Order;

use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Resources\Order\OrderItemResource;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrderItemResourceTest extends TestCase
{
    public function test_the_band_a_seated_item_was_charged_at_is_exposed(): void
    {
        $item = (new OrderItemDomainObject)
            ->setId(1)
            ->setOrderId(2)
            ->setProductId(3)
            ->setTotalBeforeAdditions(30.0)
            ->setPrice(30.0)
            ->setQuantity(1)
            ->setBandKey('b_premium');

        $this->assertSame('b_premium', (new OrderItemResource($item))->resolve(Request::create('/'))['band_key']);
    }
}
