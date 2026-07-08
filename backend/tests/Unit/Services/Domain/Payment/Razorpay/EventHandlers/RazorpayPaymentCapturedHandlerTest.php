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
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayPaymentDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayPaymentPayload;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayPaymentCapturedHandler;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class RazorpayPaymentCapturedHandlerTest extends TestCase
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
    private RazorpayPaymentCapturedHandler $handler;

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

        $this->handler = new RazorpayPaymentCapturedHandler(
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

    public function testItReturnsEarlyIfPaymentAlreadyHandled(): void
    {
        $payload = $this->createPayload();

        $this->cacheMock->expects($this->once())
            ->method('has')
            ->with('razorpay_webhook_payment_pay_123')
            ->willReturn(true);
        
        $this->dbConnectionMock->expects($this->never())->method('transaction');

        $this->handler->handleEvent($payload);
    }

    public function testItReturnsEarlyIfRazorpayOrderNotFoundInDatabase(): void
    {
        $payload = $this->createPayload();

        $this->cacheMock->method('has')->willReturn(false);

        $this->razorpayOrdersRepoMock->expects($this->once())
            ->method('findByPaymentId')
            ->with('pay_123')
            ->willReturn(null); 

        $this->orderRepoMock->expects($this->never())->method('findById');

        $this->handler->handleEvent($payload);
    }

    public function testItProcessesPaymentSuccessfullyAndUpdatesOrderAndAffiliate(): void
    {
        $payload = $this->createPayload();
        $this->cacheMock->method('has')->willReturn(false);

        $razorpayOrderMock = $this->createMock(RazorpayOrderDomainObject::class);
        $razorpayOrderMock->method('toArray')->willReturn(['order_id' => 10]);
        $this->razorpayOrdersRepoMock->method('findByPaymentId')->willReturn($razorpayOrderMock);

        $orderMock = $this->createMock(OrderDomainObject::class);
        $orderMock->method('toArray')->willReturn([
            'payment_status' => OrderPaymentStatus::AWAITING_PAYMENT->name,
            'affiliate_id' => 99 
        ]);
        $orderMock->method('getId')->willReturn(10);
        $orderMock->method('getTotalGross')->willReturn(500.00);
        $orderMock->method('getCurrency')->willReturn('INR');

        $updatedOrderMock = clone $orderMock;

        $this->orderRepoMock->method('findById')->willReturn($orderMock);
        $this->orderRepoMock->method('updateFromArray')->willReturn($updatedOrderMock);

        
        $this->razorpayOrdersRepoMock->expects($this->once())
            ->method('updateByOrderId')
            ->with(10, $this->callback(fn($data) => $data['status'] === 'captured' && (int)$data['amount'] === 50000));

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
            ->with('razorpay_webhook_payment_pay_123', true);

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
        $razorpayOrderMock->method('toArray')->willReturn(['order_id' => 10]);
        $this->razorpayOrdersRepoMock->method('findByPaymentId')->willReturn($razorpayOrderMock);

        $orderMock = $this->createMock(OrderDomainObject::class);
        $orderMock->method('toArray')->willReturn([
            'payment_status' => OrderPaymentStatus::PAYMENT_RECEIVED->name,
        ]);
        $orderMock->method('getId')->willReturn(10);
        $orderMock->method('getCurrency')->willReturn('INR');

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

    
    
    

    private function createPayload(): RazorpayPaymentPayload
    {
        $paymentDTO = new RazorpayPaymentDTO(
            id: 'pay_123',
            entity: 'payment',
            amount: 50000,
            currency: 'INR',
            status: 'captured',
            method: 'card',
            order_id: 'order_123',
            fee: 100,
            tax: 18,
            description: 'Test payment',
            notes: [],
            vpa: null,
            email: 'test@example.com',
            contact: '+919876543210',
            created_at: null,
            error: null
        );

        return new RazorpayPaymentPayload($paymentDTO);
    }
}