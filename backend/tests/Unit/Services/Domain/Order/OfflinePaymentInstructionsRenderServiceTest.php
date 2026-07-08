<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Order\OfflinePaymentInstructionsRenderService;
use Tests\TestCase;

class OfflinePaymentInstructionsRenderServiceTest extends TestCase
{
    public function test_order_tokens_are_rendered_into_instructions(): void
    {
        $order = $this->makeOrder('<p>Use {{ order.number }} for {{ event.title }}</p>');

        $this->service()->renderForOrder($order);

        $this->assertSame(
            '<p>Use ORD-12345 for Summer Session</p>',
            $order->getEvent()->getEventSettings()->getOfflinePaymentInstructions(),
        );
    }

    public function test_rendered_instructions_are_purified(): void
    {
        $order = $this->makeOrder(
            '<p>Reference {{ order.first_name }}</p>',
            orderFirstName: '<script>alert("xss")</script>Jane',
        );

        $this->service()->renderForOrder($order);

        $instructions = $order->getEvent()->getEventSettings()->getOfflinePaymentInstructions();

        $this->assertStringNotContainsString('<script>', $instructions);
        $this->assertStringContainsString('Jane', $instructions);
    }

    public function test_order_without_event_is_left_untouched(): void
    {
        $order = (new OrderDomainObject())->setId(30);

        $this->service()->renderForOrder($order);

        $this->assertNull($order->getEvent());
    }

    public function test_empty_instructions_are_left_untouched(): void
    {
        $order = $this->makeOrder(null);

        $this->service()->renderForOrder($order);

        $this->assertNull($order->getEvent()->getEventSettings()->getOfflinePaymentInstructions());
    }

    public function test_render_failure_leaves_original_instructions(): void
    {
        $order = $this->makeOrder('<p>Use {{ order.number }}</p>');
        $order->getEvent()->setStartDate('not-a-valid-date');

        $this->service()->renderForOrder($order);

        $this->assertSame(
            '<p>Use {{ order.number }}</p>',
            $order->getEvent()->getEventSettings()->getOfflinePaymentInstructions(),
        );
    }

    private function service(): OfflinePaymentInstructionsRenderService
    {
        return app(OfflinePaymentInstructionsRenderService::class);
    }

    private function makeOrder(
        ?string $offlinePaymentInstructions,
        string  $orderFirstName = 'Jane',
    ): OrderDomainObject
    {
        $organizer = (new OrganizerDomainObject())
            ->setId(1)
            ->setName('Example Organizer')
            ->setEmail('organizer@example.com');

        $settings = (new EventSettingDomainObject())
            ->setId(20)
            ->setEventId(10)
            ->setPaymentProviders([PaymentProviders::OFFLINE->value])
            ->setSupportEmail('support@example.com')
            ->setOfflinePaymentInstructions($offlinePaymentInstructions);

        $event = (new EventDomainObject())
            ->setId(10)
            ->setTitle('Summer Session')
            ->setDescription('An evening event')
            ->setStartDate('2026-08-15 18:00:00')
            ->setCurrency('GBP')
            ->setTimezone('UTC')
            ->setOrganizer($organizer)
            ->setEventSettings($settings);

        return (new OrderDomainObject())
            ->setId(30)
            ->setEventId(10)
            ->setShortId('order-short-id')
            ->setPublicId('ORD-12345')
            ->setFirstName($orderFirstName)
            ->setLastName('Buyer')
            ->setEmail('buyer@example.com')
            ->setTotalGross(125.50)
            ->setCurrency('GBP')
            ->setCreatedAt('2026-08-01 12:00:00')
            ->setStatus(OrderStatus::AWAITING_OFFLINE_PAYMENT->name)
            ->setPaymentStatus(OrderPaymentStatus::AWAITING_OFFLINE_PAYMENT->name)
            ->setPaymentProvider(PaymentProviders::OFFLINE->value)
            ->setEvent($event);
    }
}
