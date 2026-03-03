<?php

namespace Tests\Unit\Services\Domain\Payment\Razorpay\EventHandlers;

use Exception;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayRefundDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayRefundPayload;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayRefundHandler;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Log\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class RazorpayRefundHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orderRepoMock;
    private RazorpayOrdersRepositoryInterface&MockObject $razorpayOrdersRepoMock;
    private OrderRefundRepositoryInterface&MockObject $refundRepoMock;
    private ConnectionInterface&MockObject $dbConnectionMock;
    private Logger&MockObject $loggerMock;
    private CacheRepository&MockObject $cacheMock;
    private RazorpayRefundHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepoMock = $this->createMock(OrderRepositoryInterface::class);
        $this->razorpayOrdersRepoMock = $this->createMock(RazorpayOrdersRepositoryInterface::class);
        $this->refundRepoMock = $this->createMock(OrderRefundRepositoryInterface::class);
        $this->dbConnectionMock = $this->createMock(ConnectionInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->cacheMock = $this->createMock(CacheRepository::class);

        $this->orderRepoMock->method('loadRelation')->willReturnSelf();

        $this->dbConnectionMock->method('transaction')->willReturnCallback(function (callable $callback) {
            return $callback();
        });

        $this->handler = new RazorpayRefundHandler(
            $this->orderRepoMock,
            $this->razorpayOrdersRepoMock,
            $this->refundRepoMock,
            $this->dbConnectionMock,
            $this->loggerMock,
            $this->cacheMock
        );
    }

    public function testItReturnsEarlyIfEventAlreadyHandled(): void
    {
        $payload = $this->createPayload();

        $this->cacheMock->expects($this->once())
            ->method('has')
            ->with('razorpay_refund_rfnd_123')
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

    public function testItThrowsExceptionIfLocalOrderNotFound(): void
    {
        $payload = $this->createPayload();
        $this->cacheMock->method('has')->willReturn(false);

        $razorpayOrderMock = $this->createMock(RazorpayOrderDomainObject::class);
        $razorpayOrderMock->method('getOrderId')->willReturn(10);

        $this->razorpayOrdersRepoMock->method('findByPaymentId')->willReturn($razorpayOrderMock);

        $this->orderRepoMock->expects($this->once())
            ->method('findById')
            ->with(10)
            ->willThrowException(new Exception('Order not found'));

        $this->refundRepoMock->expects($this->never())->method('create');

        $this->expectException(Exception::class);

        $this->handler->handleEvent($payload);
    }

    public function testItProcessesPartialRefundSuccessfully(): void
    {
        $payload = $this->createPayload(20000);
        $this->cacheMock->method('has')->willReturn(false);

        $razorpayOrderMock = $this->createMock(RazorpayOrderDomainObject::class);
        $razorpayOrderMock->method('getOrderId')->willReturn(10);
        $this->razorpayOrdersRepoMock->method('findByPaymentId')->willReturn($razorpayOrderMock);

        $orderMock = $this->createMock(OrderDomainObject::class);
        $orderMock->method('getId')->willReturn(10);
        $orderMock->method('getTotalGross')->willReturn(500.00);

        $this->orderRepoMock->method('findById')->willReturn($orderMock);

        $this->refundRepoMock->expects($this->once())
            ->method('getTotalRefundedForOrder')
            ->with(10)
            ->willReturn(200.00);

        $this->refundRepoMock->expects($this->once())
            ->method('create')
            ->with([
                'order_id' => 10,
                'payment_provider' => 'razorpay',
                'refund_id' => 'rfnd_123',
                'amount' => 200.00,
                'currency' => 'INR',
                'status' => 'processed',
                'reason' => 'customer requested',
                'metadata' => [
                    'razorpay_refund' => $payload->refund->toArray(),
                ],
            ]);

        $this->orderRepoMock->expects($this->once())
            ->method('updateFromArray')
            ->with(10, ['payment_status' => OrderPaymentStatus::PARTIALLY_REFUNDED->name]);

        $this->cacheMock->expects($this->once())->method('put');

        $this->handler->handleEvent($payload);
    }

    public function testItProcessesFullRefundSuccessfully(): void
    {
        $payload = $this->createPayload(50000);
        $this->cacheMock->method('has')->willReturn(false);

        $razorpayOrderMock = $this->createMock(RazorpayOrderDomainObject::class);
        $razorpayOrderMock->method('getOrderId')->willReturn(10);
        $this->razorpayOrdersRepoMock->method('findByPaymentId')->willReturn($razorpayOrderMock);

        $orderMock = $this->createMock(OrderDomainObject::class);
        $orderMock->method('getId')->willReturn(10);
        $orderMock->method('getTotalGross')->willReturn(500.00);

        $this->orderRepoMock->method('findById')->willReturn($orderMock);

        $this->refundRepoMock->expects($this->once())
            ->method('getTotalRefundedForOrder')
            ->with(10)
            ->willReturn(500.00);

        $this->orderRepoMock->expects($this->once())
            ->method('updateFromArray')
            ->with(10, ['payment_status' => OrderPaymentStatus::REFUNDED->name]);

        $this->handler->handleEvent($payload);
    }

    private function createPayload(int $amountInPaise = 50000): RazorpayRefundPayload
    {
        return new RazorpayRefundPayload(
            refund: new RazorpayRefundDTO(
                id: 'rfnd_123',
                entity: 'refund',
                amount: $amountInPaise,
                currency: 'INR',
                payment_id: 'pay_123',
                status: 'processed',
                created_at: time(),
                notes: ['reason' => 'customer requested'],
                fee: 10,
                tax: 13
            )
        );
    }
}