<?php

namespace Tests\Unit\Services\Domain\Payment\Razorpay\EventHandlers;

use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayErrorDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayPaymentDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayPaymentPayload;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayPaymentFailedHandler;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Log\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class RazorpayPaymentFailedHandlerTest extends TestCase
{
    private RazorpayOrdersRepositoryInterface&MockObject $razorpayOrdersRepoMock;
    private ConnectionInterface&MockObject $dbConnectionMock;
    private Logger&MockObject $loggerMock;
    private CacheRepository&MockObject $cacheMock;
    private RazorpayPaymentFailedHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->razorpayOrdersRepoMock = $this->createMock(RazorpayOrdersRepositoryInterface::class);
        $this->dbConnectionMock = $this->createMock(ConnectionInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->cacheMock = $this->createMock(CacheRepository::class);

        $this->dbConnectionMock->method('transaction')->willReturnCallback(function (callable $callback) {
            return $callback();
        });

        $this->handler = new RazorpayPaymentFailedHandler(
            $this->razorpayOrdersRepoMock,
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
            ->with('razorpay_failed_pay_123')
            ->willReturn(true);

        $this->dbConnectionMock->expects($this->never())->method('transaction');

        $this->handler->handleEvent($payload);
    }

    public function testItReturnsEarlyIfRazorpayOrderNotFound(): void
    {
        $payload = $this->createPayload();
        $this->cacheMock->method('has')->willReturn(false);

        $this->razorpayOrdersRepoMock->method('findByPaymentId')->willReturn(null);
        $this->razorpayOrdersRepoMock->method('findByRazorpayOrderId')->willReturn(null);

        $this->razorpayOrdersRepoMock->expects($this->never())->method('updateByOrderId');

        $this->handler->handleEvent($payload);
    }

    public function testItRecordsFailedPaymentSuccessfully(): void
    {
        $payload = $this->createPayload();
        $this->cacheMock->method('has')->willReturn(false);

        $razorpayOrderMock = $this->createMock(RazorpayOrderDomainObject::class);
        $razorpayOrderMock->method('getOrderId')->willReturn(10);

        $this->razorpayOrdersRepoMock->method('findByPaymentId')->willReturn($razorpayOrderMock);

        $this->razorpayOrdersRepoMock->expects($this->once())
            ->method('updateByOrderId')
            ->with(10, $this->callback(function (array $data) {
                return $data['status'] === 'failed' &&
                    $data['failure_reason'] === 'Bad PIN' &&
                    $data['error_code'] === 'BAD_PIN';
            }));

        $this->cacheMock->expects($this->once())
            ->method('put')
            ->with('razorpay_failed_pay_123', true);

        $this->handler->handleEvent($payload);
    }

    public function testItFallsBackToOrderIdIfPaymentIdNotFound(): void
    {
        $payload = $this->createPayload();
        $this->cacheMock->method('has')->willReturn(false);

        $razorpayOrderMock = $this->createMock(RazorpayOrderDomainObject::class);
        $razorpayOrderMock->method('getOrderId')->willReturn(10);

        $this->razorpayOrdersRepoMock->method('findByPaymentId')->willReturn(null);
        $this->razorpayOrdersRepoMock->method('findByRazorpayOrderId')
            ->with('order_rzp_123')
            ->willReturn($razorpayOrderMock);

        $this->razorpayOrdersRepoMock->expects($this->once())
            ->method('updateByOrderId')
            ->with(10, $this->isType('array'));

        $this->handler->handleEvent($payload);
    }

    private function createPayload(): RazorpayPaymentPayload
    {
        $error = new RazorpayErrorDTO(
            code: 'BAD_PIN',
            description: 'Bad PIN',
            source: 'bank',
            step: 'payment_authentication',
            reason: 'payment_failed',
        );

        return new RazorpayPaymentPayload(
            payment: new RazorpayPaymentDTO(
                id: 'pay_123',
                entity: 'payment',
                amount: 50000,
                currency: 'INR',
                status: 'failed',
                method: 'card',
                order_id: 'order_rzp_123',
                fee: 0,
                tax: 0,
                description: 'Test failed payment',
                notes: [],
                vpa: null,
                email: 'test@example.com',
                contact: '+919876543210',
                created_at: time(),
                error: $error
            )
        );
    }
}