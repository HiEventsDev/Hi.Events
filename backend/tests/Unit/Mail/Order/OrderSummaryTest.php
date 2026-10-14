<?php

namespace Tests\Unit\Mail\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Order\OrderSummary;
use Tests\TestCase;

class OrderSummaryTest extends TestCase
{
    public function test_shows_the_orders_date_rather_than_the_events_first_date(): void
    {
        $html = $this->mailFor([
            $this->occurrence(2, '2026-11-12 18:00:00', 'Late show'),
        ])->render();

        $this->assertStringContainsString('Your order for Jazz Nights on November 12, 2026 at 6:00 PM was successful', $html);
        $this->assertStringContainsString('Late show', $html);
        $this->assertStringNotContainsString('October 1, 2026', $html);
    }

    private function mailFor(array $orderOccurrences): OrderSummary
    {
        $event = (new EventDomainObject)
            ->setId(10)
            ->setTitle('Jazz Nights')
            ->setTimezone('UTC')
            ->setCurrency('USD')
            ->setEventOccurrences(collect([
                $this->occurrence(9, '2026-10-01 18:00:00'),
                ...$orderOccurrences,
            ]));

        $order = (new OrderDomainObject)
            ->setId(30)
            ->setShortId('o_short')
            ->setPublicId('ORD-1')
            ->setTotalGross(0)
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setOrderItems(collect(array_map(
                fn (EventOccurrenceDomainObject $occurrence) => (new OrderItemDomainObject)->setEventOccurrence($occurrence),
                $orderOccurrences,
            )));

        return new OrderSummary(
            order: $order,
            event: $event,
            organizer: (new OrganizerDomainObject)->setName('Organizer')->setEmail('organizer@example.com'),
            eventSettings: (new EventSettingDomainObject)->setSupportEmail('support@example.com'),
            invoice: null,
        );
    }

    private function occurrence(int $id, string $start, ?string $label = null): EventOccurrenceDomainObject
    {
        return (new EventOccurrenceDomainObject)
            ->setId($id)
            ->setStartDate($start)
            ->setLabel($label);
    }
}
