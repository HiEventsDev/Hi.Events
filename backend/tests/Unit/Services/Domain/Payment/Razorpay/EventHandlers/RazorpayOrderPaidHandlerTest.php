<?php

namespace Tests\Unit\Services\Domain\Payment\Razorpay\EventHandlers;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayOrderDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayOrderPaidPayload;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayPaymentDTO;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayOrderPaidHandler;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class RazorpayOrderPaidHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orderRepoMock;
    private RazorpayOrdersRepositoryInterface&MockObject $razorpayOrdersRepoMock;
    private AffiliateRepositoryInterface&MockObject $affiliateRepoMock;
    private ProductQuantityUpdateService&MockObject $quantityUpdateServiceMock;
    private AttendeeRepositoryInterface&MockObject $attendeeRepoMock;
    private ConnectionInterface&MockObject $dbConnectionMock;
    private Logger&MockObject $loggerMock;
    private CacheRepository&MockObject $cacheMock;
    private DomainEventDispatcherService&MockObject $eventDispatcherMock;
    private OrderApplicationFeeService&MockObject $feeServiceMock;
    private RazorpayOrderPaidHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepoMock = $this->createMock(OrderRepositoryInterface::class);
        $this->razorpayOrdersRepoMock = $this->createMock(RazorpayOrdersRepositoryInterface::class);
        $this->affiliateRepoMock = $this->createMock(AffiliateRepositoryInterface::class);
        $this->quantityUpdateServiceMock = $this->createMock(ProductQuantityUpdateService::class);
        $this->attendeeRepoMock = $this->createMock(AttendeeRepositoryInterface::class);
        $this->dbConnectionMock = $this->createMock(ConnectionInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->cacheMock = $this->createMock(CacheRepository::class);
        $this->eventDispatcherMock = $this->createMock(DomainEventDispatcherService::class);
        $this->feeServiceMock = $this->createMock(OrderApplicationFeeService::class);

        $this->orderRepoMock->method('loadRelation')->willReturnSelf();


        $this->dbConnectionMock->method('transaction')->willReturnCallback(function (callable $callback) {
            return $callback();
        });

        Event::fake();

        $this->handler = new RazorpayOrderPaidHandler(
            $this->orderRepoMock,
            $this->razorpayOrdersRepoMock,
            $this->affiliateRepoMock,
            $this->quantityUpdateServiceMock,
            $this->attendeeRepoMock,
            $this->dbConnectionMock,
            $this->loggerMock,
            $this->cacheMock,
            $this->eventDispatcherMock,
            $this->feeServiceMock
        );
    }

    public function testItReturnsEarlyIfEventAlreadyHandled(): void
    {
        $payload = $this->createPayload();

        $this->cacheMock->expects($this->once())
            ->method('has')
            ->with('razorpay_order_paid_order_rzp_123')
            ->willReturn(true);

        $this->dbConnectionMock->expects($this->never())->method('transaction');

        $this->handler->handleEvent($payload);
    }

    public function testItReturnsEarlyIfRazorpayOrderNotFoundInDatabase(): void
    {
        $payload = $this->createPayload();

        $this->cacheMock->method('has')->willReturn(false);

        $this->razorpayOrdersRepoMock->expects($this->once())
            ->method('findByRazorpayOrderId')
            ->with('order_rzp_123')
            ->willReturn(null);

        $this->orderRepoMock->expects($this->never())->method('findById');

        $this->handler->handleEvent($payload);
    }

    public function testItThrowsExceptionIfLocalOrderNotFound(): void
    {
        $payload = $this->createPayload();
        $this->cacheMock->method('has')->willReturn(false);

        $razorpayOrderMock = $this->createMock(RazorpayOrderDomainObject::class);
        $razorpayOrderMock->method('getOrderId')->willReturn(10);

        $this->razorpayOrdersRepoMock->method('findByRazorpayOrderId')->willReturn($razorpayOrderMock);

        // Instruct the mock to throw an exception, simulating the repository failing to find the record
        $this->orderRepoMock->expects($this->once())
            ->method('findById')
            ->with(10)
            ->willThrowException(new \Exception('Order not found'));

        // We expect the exception to bubble up and abort the process
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        // We also strictly verify that NO database updates happen if the order isn't found
        $this->razorpayOrdersRepoMock->expects($this->never())->method('updateByOrderId');

        $this->handler->handleEvent($payload);
    }

    public function testItProcessesOrderSuccessfullyAndUpdatesRelatedEntities(): void
    {
        $payload = $this->createPayload();
        $this->cacheMock->method('has')->willReturn(false);

        $razorpayOrderMock = $this->createMock(RazorpayOrderDomainObject::class);
        $razorpayOrderMock->method('getOrderId')->willReturn(10);
        $this->razorpayOrdersRepoMock->method('findByRazorpayOrderId')->willReturn($razorpayOrderMock);

        $orderMock = $this->createMock(OrderDomainObject::class);
        $orderMock->method('getPaymentStatus')->willReturn(OrderPaymentStatus::AWAITING_PAYMENT->name);
        $orderMock->method('getId')->willReturn(10);
        $orderMock->method('getTotalGross')->willReturn(500.00);
        $orderMock->method('toArray')->willReturn(['affiliate_id' => 99]);

        $updatedOrderMock = clone $orderMock;

        $this->orderRepoMock->method('findById')->willReturn($orderMock);
        $this->orderRepoMock->method('updateFromArray')->willReturn($updatedOrderMock);


        $this->razorpayOrdersRepoMock->expects($this->once())
            ->method('updateByOrderId')
            ->with(10, $this->callback(fn($data) => $data['status'] === 'captured' && (int) $data['amount'] === 50000));

        $this->orderRepoMock->expects($this->once())
            ->method('updateFromArray')
            ->with(10, [
                'payment_status' => OrderPaymentStatus::PAYMENT_RECEIVED->name,
                'status' => OrderStatus::COMPLETED->name,
                'payment_provider' => PaymentProviders::RAZORPAY->value,
            ]);

        $this->attendeeRepoMock->expects($this->once())
            ->method('updateWhere')
            ->with(['status' => AttendeeStatus::ACTIVE->name], ['order_id' => 10, 'status' => AttendeeStatus::AWAITING_PAYMENT->name]);

        $this->quantityUpdateServiceMock->expects($this->once())->method('updateQuantitiesFromOrder');

        $this->affiliateRepoMock->expects($this->once())
            ->method('incrementSales')
            ->with(99, 500.00);

        $this->feeServiceMock->expects($this->once())
            ->method('createOrderApplicationFee')
            ->with(10, 100, OrderApplicationFeeStatus::PAID, PaymentProviders::RAZORPAY, 'INR');

        $this->cacheMock->expects($this->once())
            ->method('put')
            ->with('razorpay_order_paid_order_rzp_123', true);

        $this->eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(OrderEvent::class));

        $this->handler->handleEvent($payload);

        Event::assertDispatched(OrderStatusChangedEvent::class);
    }

    public function testItSkipsOrderUpdatesIfAlreadyPaid(): void
    {
        $payload = $this->createPayload();
        $this->cacheMock->method('has')->willReturn(false);

        $razorpayOrderMock = $this->createMock(RazorpayOrderDomainObject::class);
        $razorpayOrderMock->method('getOrderId')->willReturn(10);
        $this->razorpayOrdersRepoMock->method('findByRazorpayOrderId')->willReturn($razorpayOrderMock);


        $orderMock = $this->createMock(OrderDomainObject::class);
        $orderMock->method('getPaymentStatus')->willReturn(OrderPaymentStatus::PAYMENT_RECEIVED->name);
        $orderMock->method('getId')->willReturn(10);

        $this->orderRepoMock->method('findById')->willReturn($orderMock);

        $this->orderRepoMock->expects($this->never())->method('updateFromArray');
        $this->attendeeRepoMock->expects($this->never())->method('updateWhere');
        $this->affiliateRepoMock->expects($this->never())->method('incrementSales');
        $this->eventDispatcherMock->expects($this->never())->method('dispatch');

        $this->feeServiceMock->expects($this->once())->method('createOrderApplicationFee');
        $this->cacheMock->expects($this->once())->method('put');

        $this->handler->handleEvent($payload);

        Event::assertNotDispatched(OrderStatusChangedEvent::class);
    }

    private function createPayload(): RazorpayOrderPaidPayload
    {
        return new RazorpayOrderPaidPayload(
            order: $this->createDummyOrderDTO(),
            payment: $this->createDummyPaymentDTO()
        );
    }

    private function createDummyOrderDTO(): RazorpayOrderDTO
    {
        return new RazorpayOrderDTO(
            id: 'order_rzp_123',
            entity: 'order',
            amount: 50000,
            amount_paid: 50000,
            amount_due: 0,
            currency: 'INR',
            receipt: 'receipt_123',
            status: 'paid',
            created_at: time(),
            notes: []
        );
    }

    private function createDummyPaymentDTO(): RazorpayPaymentDTO
    {
        return new RazorpayPaymentDTO(
            id: 'pay_123',
            entity: 'payment',
            amount: 50000,
            currency: 'INR',
            status: 'captured',
            method: 'card',
            order_id: 'order_rzp_123',
            fee: 100,
            tax: 18,
            description: 'Test payment',
            notes: [],
            vpa: null,
            email: 'test@example.com',
            contact: '+919876543210',
            created_at: time(),
            error: null

        );
    }
}