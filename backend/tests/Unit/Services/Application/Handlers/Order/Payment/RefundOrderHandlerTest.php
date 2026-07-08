<?php

namespace Tests\Unit\Services\Application\Handlers\Order\Payment;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\RefundOrderHandler;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\Payment\IdempotentRefundService;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Mail\PendingMail;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class RefundOrderHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orderRepoMock;
    private EventRepositoryInterface&MockObject $eventRepoMock;
    private Mailer&MockObject $mailerMock;
    private OrderCancelService&MockObject $orderCancelServiceMock;
    private ConnectionInterface&MockObject $dbConnectionMock;
    private StripePaymentIntentRefundService&MockObject $stripeRefundServiceMock;
    private StripeClientFactory&MockObject $stripeClientFactory;
    private IdempotentRefundService&MockObject $idempotentRefundServiceMock;
    private Repository&MockObject $configMock;
    private RefundOrderHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepoMock = $this->createMock(OrderRepositoryInterface::class);
        $this->eventRepoMock = $this->createMock(EventRepositoryInterface::class);
        $this->mailerMock = $this->createMock(Mailer::class);
        $this->orderCancelServiceMock = $this->createMock(OrderCancelService::class);
        $this->dbConnectionMock = $this->createMock(ConnectionInterface::class);
        $this->stripeRefundServiceMock = $this->createMock(StripePaymentIntentRefundService::class);
        $this->stripeClientFactory = $this->createMock(StripeClientFactory::class);
        $this->idempotentRefundServiceMock = $this->createMock(IdempotentRefundService::class);
        $this->configMock = $this->createMock(Repository::class);

        $this->handler = new RefundOrderHandler(
            $this->orderRepoMock,
            $this->eventRepoMock,
            $this->mailerMock,
            $this->orderCancelServiceMock,
            $this->dbConnectionMock,
            $this->stripeRefundServiceMock,
            $this->stripeClientFactory,
            $this->idempotentRefundServiceMock,
            $this->configMock
        );
    }

    private function createOrderWithStripePayment(): OrderDomainObject&MockObject
    {
        $order = $this->createMock(OrderDomainObject::class);
        $order->method('getId')->willReturn(1);
        $order->method('getEventId')->willReturn(1);
        $order->method('getRefundStatus')->willReturn(null);
        $order->method('getStripePayment')->willReturn($this->createMock(StripePaymentDomainObject::class));
        $order->method('getRazorpayOrder')->willReturn(null);
        $order->method('getEmail')->willReturn('test@example.com');
        $order->method('getLocale')->willReturn('en');
        $order->method('getCurrency')->willReturn('USD');
        return $order;
    }

    private function createOrderWithRazorpayPayment(): OrderDomainObject&MockObject
    {
        $order = $this->createMock(OrderDomainObject::class);
        $order->method('getId')->willReturn(2);
        $order->method('getEventId')->willReturn(1);
        $order->method('getRefundStatus')->willReturn(null);
        $order->method('getStripePayment')->willReturn(null);
        $order->method('getRazorpayOrder')->willReturn($this->createMock(RazorpayOrderDomainObject::class));
        $order->method('getEmail')->willReturn('test@example.com');
        $order->method('getLocale')->willReturn('en');
        $order->method('getCurrency')->willReturn('INR');
        return $order;
    }

    private function createEvent(): EventDomainObject&MockObject
    {
        $organizerMock = $this->createMock(OrganizerDomainObject::class);
        $settingsMock = $this->createMock(EventSettingDomainObject::class);

        $event = $this->createMock(EventDomainObject::class);
        $event->method('getOrganizer')->willReturn($organizerMock);
        $event->method('getEventSettings')->willReturn($settingsMock);
        return $event;
    }

    public function testRefundsStripeOrderSuccessfully(): void
    {
        $dto = new RefundOrderDTO(
            event_id: 1,
            order_id: 1,
            amount: 50.00,
            cancel_order: false,
            notify_buyer: false
        );

        $order = $this->createOrderWithStripePayment();
        $event = $this->createEvent();

        $this->dbConnectionMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn($callback) => $callback());

        $this->orderRepoMock->expects($this->exactly(2))
            ->method('loadRelation')
            ->willReturnSelf();

        $this->orderRepoMock->expects($this->once())
            ->method('findFirstWhere')
            ->with(['event_id' => 1, 'id' => 1])
            ->willReturn($order);

        $this->eventRepoMock->expects($this->exactly(2))
            ->method('loadRelation')
            ->willReturnSelf();

        $this->eventRepoMock->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($event);

        $this->stripeRefundServiceMock->expects($this->once())
            ->method('refundPayment')
            ->with(
                $this->callback(fn(MoneyValue $amount) => $amount->toFloat() === 50.00),
                $order->getStripePayment()
            );

        $this->orderRepoMock->expects($this->once())
            ->method('updateFromArray')
            ->with(1, $this->callback(fn($attrs) => isset($attrs['refund_status'])))
            ->willReturn($order);

        $result = $this->handler->handle($dto);

        $this->assertSame($order, $result);
    }

    public function testRefundsRazorpayOrderWithIdempotencyEnabled(): void
    {
        $this->configMock->method('get')
            ->with('refunds.razorpay.idempotency_enabled')
            ->willReturn(true);

        $dto = new RefundOrderDTO(
            event_id: 1,
            order_id: 2,
            amount: 100.00,
            cancel_order: false,
            notify_buyer: false,
            provider_options: ['speed' => 'optimum', 'receipt' => 'Refund#123']
        );

        $order = $this->createOrderWithRazorpayPayment();
        $event = $this->createEvent();

        $this->dbConnectionMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn($callback) => $callback());

        $this->orderRepoMock->expects($this->exactly(2))
            ->method('loadRelation')
            ->willReturnSelf();

        $this->orderRepoMock->expects($this->once())
            ->method('findFirstWhere')
            ->with(['event_id' => 1, 'id' => 2])
            ->willReturn($order);

        $this->eventRepoMock->expects($this->exactly(2))
            ->method('loadRelation')
            ->willReturnSelf();

        $this->eventRepoMock->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($event);

        $this->idempotentRefundServiceMock->expects($this->once())
            ->method('refundWithIdempotency')
            ->with(
                $order->getRazorpayOrder(),
                $this->callback(fn(MoneyValue $amount) => $amount->toFloat() === 100.00),
                ['speed' => 'optimum', 'receipt' => 'Refund#123'],
                $this->callback(function ($key) {
                    return is_string($key)
                        && str_starts_with($key, 'refund_')
                        && strlen($key) === 71
                        && ctype_xdigit(substr($key, 7));
                })
            );

        $this->orderRepoMock->expects($this->once())
            ->method('updateFromArray')
            ->with(2, $this->callback(fn($attrs) => isset($attrs['refund_status'])))
            ->willReturn($order);

        $result = $this->handler->handle($dto);

        $this->assertSame($order, $result);
    }

    public function testSkipsIdempotencyKeyWhenDisabled(): void
    {
        $this->configMock->method('get')
            ->with('refunds.razorpay.idempotency_enabled')
            ->willReturn(false);

        $dto = new RefundOrderDTO(
            event_id: 1,
            order_id: 2,
            amount: 100.00,
            cancel_order: false,
            notify_buyer: false
        );

        $order = $this->createOrderWithRazorpayPayment();
        $event = $this->createEvent();

        $this->dbConnectionMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn($callback) => $callback());

        $this->orderRepoMock->method('loadRelation')->willReturnSelf();
        $this->orderRepoMock->method('findFirstWhere')->willReturn($order);
        $this->eventRepoMock->method('loadRelation')->willReturnSelf();
        $this->eventRepoMock->method('findById')->willReturn($event);

        $this->idempotentRefundServiceMock->expects($this->once())
            ->method('refundWithIdempotency')
            ->with(
                $order->getRazorpayOrder(),
                $this->callback(fn(MoneyValue $amount) => $amount->toFloat() === 100.00),
                [],
                null
            );

        $this->orderRepoMock->method('updateFromArray')->willReturn($order);

        $this->handler->handle($dto);
    }

    public function testThrowsExceptionWhenNoPaymentDataExists(): void
    {
        $dto = new RefundOrderDTO(
            event_id: 1,
            order_id: 3,
            amount: 10.00,
            cancel_order: false,
            notify_buyer: false
        );

        $order = $this->createMock(OrderDomainObject::class);
        $order->method('getStripePayment')->willReturn(null);
        $order->method('getRazorpayOrder')->willReturn(null);
        $order->method('getCurrency')->willReturn('USD');

        $this->dbConnectionMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn($callback) => $callback());

        $this->orderRepoMock->method('loadRelation')->willReturnSelf();
        $this->orderRepoMock->method('findFirstWhere')->willReturn($order);
        $this->eventRepoMock->method('loadRelation')->willReturnSelf();
        $this->eventRepoMock->method('findById')->willReturn($this->createEvent());

        $this->expectException(RefundNotPossibleException::class);
        $this->expectExceptionMessage('There is no payment data associated with this order.');

        $this->handler->handle($dto);
    }

    public function testThrowsExceptionWhenRefundAlreadyPending(): void
    {
        $dto = new RefundOrderDTO(
            event_id: 1,
            order_id: 1,
            amount: 50.00,
            cancel_order: false,
            notify_buyer: false
        );

        $order = $this->createMock(OrderDomainObject::class);
        $order->method('getId')->willReturn(1);
        $order->method('getEventId')->willReturn(1);
        $order->method('getRefundStatus')->willReturn(OrderRefundStatus::REFUND_PENDING->name);
        $order->method('getStripePayment')->willReturn($this->createMock(StripePaymentDomainObject::class));
        $order->method('getRazorpayOrder')->willReturn(null);
        $order->method('getEmail')->willReturn('test@example.com');
        $order->method('getLocale')->willReturn('en');
        $order->method('getCurrency')->willReturn('USD');

        $event = $this->createEvent();

        $this->dbConnectionMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn($callback) => $callback());

        $this->orderRepoMock->method('loadRelation')->willReturnSelf();
        $this->orderRepoMock->method('findFirstWhere')->willReturn($order);
        $this->orderRepoMock->method('updateFromArray')->willReturn($order);

        $this->eventRepoMock->method('loadRelation')->willReturnSelf();
        $this->eventRepoMock->method('findById')->willReturn($event);

        $this->expectException(RefundNotPossibleException::class);
        $this->expectExceptionMessage('There is already a refund pending for this order.');

        $this->handler->handle($dto);
    }

    public function testCancelsOrderWhenRequested(): void
    {
        $dto = new RefundOrderDTO(
            event_id: 1,
            order_id: 1,
            amount: 50.00,
            cancel_order: true,
            notify_buyer: false
        );

        $order = $this->createOrderWithStripePayment();
        $event = $this->createEvent();

        $this->dbConnectionMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn($callback) => $callback());

        $this->orderRepoMock->method('loadRelation')->willReturnSelf();
        $this->orderRepoMock->method('findFirstWhere')->willReturn($order);
        $this->eventRepoMock->method('loadRelation')->willReturnSelf();
        $this->eventRepoMock->method('findById')->willReturn($event);

        $this->orderCancelServiceMock->expects($this->once())
            ->method('cancelOrder')
            ->with($order);

        $this->stripeRefundServiceMock->method('refundPayment');
        $this->orderRepoMock->method('updateFromArray')->willReturn($order);

        $this->handler->handle($dto);
    }

    public function testNotifiesBuyerWhenRequested(): void
    {
        $dto = new RefundOrderDTO(
            event_id: 1,
            order_id: 1,
            amount: 50.00,
            cancel_order: false,
            notify_buyer: true
        );

        $order = $this->createOrderWithStripePayment();
        $event = $this->createEvent();

        $this->dbConnectionMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn($callback) => $callback());

        $this->orderRepoMock->method('loadRelation')->willReturnSelf();
        $this->orderRepoMock->method('findFirstWhere')->willReturn($order);
        $this->eventRepoMock->method('loadRelation')->willReturnSelf();
        $this->eventRepoMock->method('findById')->willReturn($event);

        $this->stripeRefundServiceMock->method('refundPayment');

        $pendingMailMock = $this->createMock(PendingMail::class);
        $pendingMailMock->expects($this->once())
            ->method('locale')
            ->with('en')
            ->willReturnSelf();
        $pendingMailMock->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(\HiEvents\Mail\Order\OrderRefunded::class));

        $this->mailerMock->expects($this->once())
            ->method('to')
            ->with('test@example.com')
            ->willReturn($pendingMailMock);

        $this->orderRepoMock->method('updateFromArray')->willReturn($order);

        $this->handler->handle($dto);
    }

    public function testHandlesRazorpayIdempotentConflict(): void
    {
        $this->configMock->method('get')
            ->with('refunds.razorpay.idempotency_enabled', true)
            ->willReturn(true);

        $dto = new RefundOrderDTO(
            event_id: 1,
            order_id: 2,
            amount: 100.00,
            cancel_order: false,
            notify_buyer: false
        );

        $order = $this->createOrderWithRazorpayPayment();
        $event = $this->createEvent();

        $this->dbConnectionMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn($callback) => $callback());

        $this->orderRepoMock->method('loadRelation')->willReturnSelf();
        $this->orderRepoMock->method('findFirstWhere')->willReturn($order);
        $this->eventRepoMock->method('loadRelation')->willReturnSelf();
        $this->eventRepoMock->method('findById')->willReturn($event);


        $this->idempotentRefundServiceMock->method('refundWithIdempotency')
            ->willThrowException(new RefundNotPossibleException('A refund for this order is already being processed.'));

        $this->expectException(RefundNotPossibleException::class);
        $this->expectExceptionMessage('already being processed');

        $this->handler->handle($dto);
    }

    public function testThrowsResourceNotFoundExceptionWhenOrderNotFound(): void
    {
        $dto = new RefundOrderDTO(
            event_id: 1,
            order_id: 999,
            amount: 50.00,
            cancel_order: false,
            notify_buyer: false
        );

        $this->dbConnectionMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn($callback) => $callback());

        $this->orderRepoMock->method('loadRelation')->willReturnSelf();
        $this->orderRepoMock->method('findFirstWhere')->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($dto);
    }
}