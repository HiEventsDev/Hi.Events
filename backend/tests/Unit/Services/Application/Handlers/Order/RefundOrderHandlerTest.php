<?php

namespace Tests\Unit\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Offline\RefundOfflineOrderHandler;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\RefundOrderHandler as RefundStripeOrderHandler;
use HiEvents\Services\Application\Handlers\Order\RefundOrderHandler;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class RefundOrderHandlerTest extends TestCase
{
    private const EVENT_ID = 10;

    private const ORDER_ID = 20;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private RefundStripeOrderHandler|MockInterface $refundStripeOrderHandler;

    private RefundOfflineOrderHandler|MockInterface $refundOfflineOrderHandler;

    private RefundOrderHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->refundStripeOrderHandler = Mockery::mock(RefundStripeOrderHandler::class);
        $this->refundOfflineOrderHandler = Mockery::mock(RefundOfflineOrderHandler::class);

        $this->handler = new RefundOrderHandler(
            $this->orderRepository,
            $this->refundStripeOrderHandler,
            $this->refundOfflineOrderHandler,
        );
    }

    public function test_an_offline_order_is_refunded_by_the_offline_handler(): void
    {
        $this->givenOrderIsFound(PaymentProviders::OFFLINE->name);
        $refundedOrder = new OrderDomainObject;

        $dto = $this->givenDTO();
        $this->refundOfflineOrderHandler->shouldReceive('handle')->once()->with($dto)->andReturn($refundedOrder);
        $this->refundStripeOrderHandler->shouldNotReceive('handle');

        $this->assertSame($refundedOrder, $this->handler->handle($dto));
    }

    public function test_a_stripe_order_is_refunded_by_the_stripe_handler(): void
    {
        $this->givenOrderIsFound(PaymentProviders::STRIPE->name);
        $refundedOrder = new OrderDomainObject;

        $dto = $this->givenDTO();
        $this->refundStripeOrderHandler->shouldReceive('handle')->once()->with($dto)->andReturn($refundedOrder);
        $this->refundOfflineOrderHandler->shouldNotReceive('handle');

        $this->assertSame($refundedOrder, $this->handler->handle($dto));
    }

    public function test_a_missing_order_throws_a_not_found_exception(): void
    {
        $this->orderRepository->shouldReceive('findFirstWhere')->once()->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($this->givenDTO());
    }

    private function givenDTO(): RefundOrderDTO
    {
        return new RefundOrderDTO(
            event_id: self::EVENT_ID,
            order_id: self::ORDER_ID,
            amount: 50.0,
            notify_buyer: false,
            cancel_order: false,
        );
    }

    private function givenOrderIsFound(string $paymentProvider): void
    {
        $order = (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setPaymentProvider($paymentProvider);

        $this->orderRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                OrderDomainObjectAbstract::EVENT_ID => self::EVENT_ID,
                OrderDomainObjectAbstract::ID => self::ORDER_ID,
            ])
            ->andReturn($order);
    }
}
