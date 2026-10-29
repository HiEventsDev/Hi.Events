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
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.is_hi_events' => false]);
    }

    public function test_shows_the_orders_date_rather_than_the_events_first_date(): void
    {
        $html = $this->mailFor([
            $this->occurrence(2, '2026-11-12 18:00:00', 'Late show'),
        ])->render();

        $this->assertStringContainsString('Your order for Jazz Nights on November 12, 2026 at 6:00 PM was successful', $html);
        $this->assertStringContainsString('Late show', $html);
        $this->assertStringNotContainsString('October 1, 2026', $html);
    }

    public function test_an_unpaid_offline_order_shows_the_order_reference_as_the_payment_reference(): void
    {
        $html = $this->mailFor([], OrderStatus::AWAITING_OFFLINE_PAYMENT, orderReferenceEnabled: true)->render();

        $this->assertStringContainsString('Payment reference', $html);
        $this->assertStringContainsString('Include this reference with your payment', $html);
    }

    public function test_an_unpaid_offline_order_is_not_called_confirmed_and_shows_the_amount_due(): void
    {
        $mail = $this->mailFor([], OrderStatus::AWAITING_OFFLINE_PAYMENT);
        $html = $mail->render();

        $this->assertSame('Order placed: payment needed', $mail->envelope()->subject);
        $this->assertStringContainsString('Order placed: payment needed', $html);
        $this->assertStringNotContainsString('Your Order is Confirmed', $html);
        $this->assertStringContainsString('Amount due', $html);
        $this->assertStringContainsString('$42.50', $html);
    }

    public function test_a_paid_order_is_still_called_confirmed(): void
    {
        $mail = $this->mailFor([]);

        $this->assertStringContainsString('Your Order is Confirmed!', $mail->envelope()->subject);
        $this->assertStringNotContainsString('Amount due', $mail->render());
    }

    public function test_the_payment_reference_is_left_out_when_the_organizer_uses_their_own(): void
    {
        $html = $this->mailFor([], OrderStatus::AWAITING_OFFLINE_PAYMENT, orderReferenceEnabled: false)->render();

        $this->assertStringContainsString('How to pay', $html);
        $this->assertStringNotContainsString('Payment reference', $html);
    }

    private function mailFor(
        array $orderOccurrences,
        OrderStatus $status = OrderStatus::COMPLETED,
        bool $orderReferenceEnabled = true,
    ): OrderSummary {
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
            ->setTotalGross(42.50)
            ->setStatus($status->name)
            ->setOrderItems(collect(array_map(
                fn (EventOccurrenceDomainObject $occurrence) => (new OrderItemDomainObject)->setEventOccurrence($occurrence),
                $orderOccurrences,
            )));

        return new OrderSummary(
            order: $order,
            event: $event,
            organizer: (new OrganizerDomainObject)->setName('Organizer')->setEmail('organizer@example.com'),
            eventSettings: (new EventSettingDomainObject)
                ->setSupportEmail('support@example.com')
                ->setOfflinePaymentOrderReferenceEnabled($orderReferenceEnabled),
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
