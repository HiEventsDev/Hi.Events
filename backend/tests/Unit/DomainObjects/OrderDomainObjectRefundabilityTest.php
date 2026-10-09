<?php

namespace Tests\Unit\DomainObjects;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use Tests\TestCase;

class OrderDomainObjectRefundabilityTest extends TestCase
{
    private function createOrder(
        ?string $paymentProvider,
        bool $isManuallyCreated = false,
        float $totalGross = 100.0,
        ?string $refundStatus = null,
    ): OrderDomainObject {
        return (new OrderDomainObject)
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setPaymentProvider($paymentProvider)
            ->setIsManuallyCreated($isManuallyCreated)
            ->setTotalGross($totalGross)
            ->setRefundStatus($refundStatus);
    }

    public function test_a_paid_manually_created_order_is_refundable_outside_the_platform(): void
    {
        $order = $this->createOrder(paymentProvider: null, isManuallyCreated: true);

        $this->assertTrue($order->isPaidOutsidePlatform());
        $this->assertTrue($order->isRefundable());
    }

    public function test_an_offline_order_is_paid_outside_the_platform(): void
    {
        $order = $this->createOrder(paymentProvider: PaymentProviders::OFFLINE->name);

        $this->assertTrue($order->isPaidOutsidePlatform());
        $this->assertTrue($order->isRefundable());
    }

    public function test_a_stripe_order_is_refundable_but_not_paid_outside_the_platform(): void
    {
        $order = $this->createOrder(paymentProvider: PaymentProviders::STRIPE->name, isManuallyCreated: true);

        $this->assertFalse($order->isPaidOutsidePlatform());
        $this->assertTrue($order->isRefundable());
    }

    public function test_an_order_without_a_provider_that_was_not_manually_created_is_not_refundable(): void
    {
        $order = $this->createOrder(paymentProvider: null);

        $this->assertFalse($order->isPaidOutsidePlatform());
        $this->assertFalse($order->isRefundable());
    }

    public function test_a_free_manually_created_order_is_not_refundable(): void
    {
        $order = $this->createOrder(paymentProvider: null, isManuallyCreated: true, totalGross: 0.0);

        $this->assertFalse($order->isRefundable());
    }

    public function test_a_fully_refunded_manually_created_order_is_not_refundable(): void
    {
        $order = $this->createOrder(
            paymentProvider: null,
            isManuallyCreated: true,
            refundStatus: OrderRefundStatus::REFUNDED->name,
        );

        $this->assertFalse($order->isRefundable());
    }
}
