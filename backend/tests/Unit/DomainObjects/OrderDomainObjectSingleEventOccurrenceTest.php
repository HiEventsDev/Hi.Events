<?php

namespace Tests\Unit\DomainObjects;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use Tests\TestCase;

class OrderDomainObjectSingleEventOccurrenceTest extends TestCase
{
    public function test_returns_the_occurrence_shared_by_every_item(): void
    {
        $occurrence = (new EventOccurrenceDomainObject)->setId(1)->setStartDate('2026-11-05 18:00:00');

        $order = (new OrderDomainObject)->setOrderItems(collect([
            (new OrderItemDomainObject)->setEventOccurrence($occurrence),
            (new OrderItemDomainObject)->setEventOccurrence($occurrence),
        ]));

        $this->assertSame($occurrence, $order->getSingleEventOccurrence());
    }

    public function test_is_null_when_items_span_several_occurrences(): void
    {
        $order = (new OrderDomainObject)->setOrderItems(collect([
            (new OrderItemDomainObject)->setEventOccurrence((new EventOccurrenceDomainObject)->setId(1)),
            (new OrderItemDomainObject)->setEventOccurrence((new EventOccurrenceDomainObject)->setId(2)),
        ]));

        $this->assertNull($order->getSingleEventOccurrence());
    }

    public function test_is_null_when_order_items_are_not_loaded(): void
    {
        $this->assertNull((new OrderDomainObject)->getSingleEventOccurrence());
    }
}
