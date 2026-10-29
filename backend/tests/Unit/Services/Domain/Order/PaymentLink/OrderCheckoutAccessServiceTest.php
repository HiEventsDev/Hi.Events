<?php

namespace Tests\Unit\Services\Domain\Order\PaymentLink;

use HiEvents\DomainObjects\Enums\OrderCheckoutAccess;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Order\PaymentLink\OrderCheckoutAccessService;
use HiEvents\Services\Domain\Order\PaymentLink\OrderPaymentLinkTokenService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Mockery;
use Tests\TestCase;

class OrderCheckoutAccessServiceTest extends TestCase
{
    public function test_the_session_that_started_the_order_is_the_owner(): void
    {
        $this->assertSame(OrderCheckoutAccess::OWNER, $this->resolve('owner', tokenValid: false));
    }

    public function test_a_valid_payment_link_token_grants_payment_access_to_a_reserved_order(): void
    {
        $this->assertSame(OrderCheckoutAccess::PAYMENT_LINK, $this->resolve('token', tokenValid: true));
    }

    public function test_a_payment_link_token_also_works_on_an_order_placed_to_pay_later(): void
    {
        $this->assertSame(
            OrderCheckoutAccess::PAYMENT_LINK,
            $this->resolve('token', tokenValid: true, status: OrderStatus::AWAITING_OFFLINE_PAYMENT),
        );
    }

    public function test_the_person_paying_is_still_recognised_once_the_order_is_paid(): void
    {
        $this->assertSame(
            OrderCheckoutAccess::PAYMENT_LINK,
            $this->resolve('token', tokenValid: true, status: OrderStatus::COMPLETED),
        );
    }

    public function test_anyone_else_has_no_access(): void
    {
        $this->assertSame(OrderCheckoutAccess::NONE, $this->resolve('stranger', tokenValid: false));
        $this->assertFalse(OrderCheckoutAccess::NONE->canPay());
        $this->assertTrue(OrderCheckoutAccess::PAYMENT_LINK->canPay());
    }

    private function resolve(string $requestSession, bool $tokenValid, OrderStatus $status = OrderStatus::RESERVED): OrderCheckoutAccess
    {
        $order = (new OrderDomainObject)->setSessionId('owner')->setStatus($status->name);

        $session = Mockery::mock(CheckoutSessionManagementService::class);
        $session->shouldReceive('verifySession')->andReturnUsing(fn (string $id) => $id === $requestSession);
        $session->shouldReceive('getSessionId')->andReturn($requestSession);

        $tokens = Mockery::mock(OrderPaymentLinkTokenService::class);
        $tokens->shouldReceive('isValidToken')->andReturn($tokenValid);

        return (new OrderCheckoutAccessService($session, $tokens))->resolve($order);
    }
}
