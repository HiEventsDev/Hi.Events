<?php

namespace Tests\Unit\Services\Application\Handlers\Order\Payment\Offline;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Mail\Order\OrderRefunded;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Offline\RefundOfflineOrderHandler;
use HiEvents\Services\Domain\Order\OfflineOrderRefundService;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Values\MoneyValue;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RefundOfflineOrderHandlerTest extends TestCase
{
    private const EVENT_ID = 10;

    private const ORDER_ID = 20;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private Mailer|MockInterface $mailer;

    private OrderCancelService|MockInterface $orderCancelService;

    private OfflineOrderRefundService|MockInterface $offlineOrderRefundService;

    private RefundOfflineOrderHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->mailer = Mockery::mock(Mailer::class);
        $this->orderCancelService = Mockery::mock(OrderCancelService::class);
        $this->offlineOrderRefundService = Mockery::mock(OfflineOrderRefundService::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->handler = new RefundOfflineOrderHandler(
            $this->orderRepository,
            $this->eventRepository,
            $this->mailer,
            $this->orderCancelService,
            $this->offlineOrderRefundService,
            $databaseManager,
        );
    }

    public function test_an_offline_order_can_be_refunded(): void
    {
        $order = $this->givenOrderIsFound();
        $refreshedOrder = $this->givenOrderIsRefreshedAfterRefund();

        $this->offlineOrderRefundService->shouldReceive('refundOrder')
            ->once()
            ->withArgs(fn (OrderDomainObject $refundOrder, MoneyValue $amount): bool => $refundOrder === $order
                && $amount->toMinorUnit() === 5000);

        $result = $this->handler->handle($this->givenDTO(amount: 50.0));

        $this->assertSame($refreshedOrder, $result);
    }

    public function test_the_order_is_cancelled_when_requested(): void
    {
        $order = $this->givenOrderIsFound();
        $this->givenOrderIsRefreshedAfterRefund();

        $this->orderCancelService->shouldReceive('cancelOrder')
            ->once()
            ->with($order);

        $this->offlineOrderRefundService->shouldReceive('refundOrder')->once();

        $this->handler->handle($this->givenDTO(amount: 50.0, cancelOrder: true));
    }

    public function test_an_already_cancelled_order_is_not_cancelled_again(): void
    {
        $this->givenOrderIsFound(status: OrderStatus::CANCELLED->name);
        $this->givenOrderIsRefreshedAfterRefund();

        $this->orderCancelService->shouldNotReceive('cancelOrder');
        $this->offlineOrderRefundService->shouldReceive('refundOrder')->once();

        $this->handler->handle($this->givenDTO(amount: 50.0, cancelOrder: true));
    }

    public function test_the_buyer_is_notified_when_requested(): void
    {
        $this->givenOrderIsFound();
        $this->givenOrderIsRefreshedAfterRefund();

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getOrganizer')->andReturn(Mockery::mock(OrganizerDomainObject::class));
        $event->shouldReceive('getEventSettings')->andReturn(
            Mockery::mock(EventSettingDomainObject::class)->shouldIgnoreMissing()
        );

        $this->eventRepository->shouldReceive('loadRelation')->twice()->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->once()->with(self::EVENT_ID)->andReturn($event);

        $this->mailer->shouldReceive('to')->once()->with('buyer@example.com')->andReturnSelf();
        $this->mailer->shouldReceive('locale')->once()->andReturnSelf();
        $this->mailer->shouldReceive('send')->once()->with(Mockery::type(OrderRefunded::class));

        $this->offlineOrderRefundService->shouldReceive('refundOrder')->once();

        $this->handler->handle($this->givenDTO(amount: 50.0, notifyBuyer: true));
    }

    public function test_a_non_offline_order_cannot_be_refunded(): void
    {
        $this->givenOrderIsFound(paymentProvider: PaymentProviders::STRIPE->name);

        $this->expectException(RefundNotPossibleException::class);

        $this->handler->handle($this->givenDTO(amount: 50.0));
    }

    public function test_an_order_awaiting_offline_payment_cannot_be_refunded(): void
    {
        $this->givenOrderIsFound(status: OrderStatus::AWAITING_OFFLINE_PAYMENT->name);

        $this->expectException(RefundNotPossibleException::class);

        $this->handler->handle($this->givenDTO(amount: 50.0));
    }

    public function test_a_fully_refunded_order_cannot_be_refunded_again(): void
    {
        $this->givenOrderIsFound(refundStatus: OrderRefundStatus::REFUNDED->name);

        $this->expectException(RefundNotPossibleException::class);

        $this->handler->handle($this->givenDTO(amount: 50.0));
    }

    public function test_the_refund_amount_cannot_exceed_the_remaining_amount(): void
    {
        $this->givenOrderIsFound(totalRefunded: 60.0);

        $this->expectException(RefundNotPossibleException::class);

        $this->handler->handle($this->givenDTO(amount: 50.0));
    }

    private function givenDTO(float $amount, bool $notifyBuyer = false, bool $cancelOrder = false): RefundOrderDTO
    {
        return new RefundOrderDTO(
            event_id: self::EVENT_ID,
            order_id: self::ORDER_ID,
            amount: $amount,
            notify_buyer: $notifyBuyer,
            cancel_order: $cancelOrder,
        );
    }

    private function givenOrderIsFound(
        string $paymentProvider = PaymentProviders::OFFLINE->name,
        string $status = OrderStatus::COMPLETED->name,
        ?string $refundStatus = null,
        float $totalRefunded = 0.0,
    ): OrderDomainObject {
        $order = (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setEventId(self::EVENT_ID)
            ->setCurrency('USD')
            ->setEmail('buyer@example.com')
            ->setPaymentProvider($paymentProvider)
            ->setStatus($status)
            ->setRefundStatus($refundStatus)
            ->setTotalGross(100.0)
            ->setTotalRefunded($totalRefunded);

        $this->orderRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['event_id' => self::EVENT_ID, 'id' => self::ORDER_ID])
            ->andReturn($order);

        return $order;
    }

    private function givenOrderIsRefreshedAfterRefund(): OrderDomainObject
    {
        $refreshedOrder = (new OrderDomainObject)->setId(self::ORDER_ID);

        $this->orderRepository->shouldReceive('findById')
            ->once()
            ->with(self::ORDER_ID)
            ->andReturn($refreshedOrder);

        return $refreshedOrder;
    }
}
