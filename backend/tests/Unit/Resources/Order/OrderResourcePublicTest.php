<?php

namespace Tests\Unit\Resources\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Resources\Order\OrderResourcePublic;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrderResourcePublicTest extends TestCase
{
    public function test_an_online_order_tells_the_buyer_when_it_was_placed(): void
    {
        $order = (new OrderDomainObject)
            ->setId(1)
            ->setEventId(2)
            ->setShortId('o_abc123')
            ->setPublicId('O-ABC123')
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setCurrency('EUR')
            ->setTotalBeforeAdditions(10.0)
            ->setTotalTax(0.0)
            ->setTotalGross(10.0)
            ->setTotalFee(0.0)
            ->setCreatedAt('2026-11-26 21:22:00');

        $resource = (new OrderResourcePublic($order))->resolve(Request::create('/'));

        $this->assertSame('2026-11-26 21:22:00', $resource['created_at']);
        $this->assertArrayNotHasKey('box_office_operator_name', $resource);
    }
}
