<?php

namespace Tests\Unit\Services\Domain\Payment;

use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\DomainObjects\RefundAttemptDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Repository\Interfaces\RefundAttemptRepositoryInterface;
use HiEvents\Services\Domain\Payment\IdempotentRefundService;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentRefundService;
use HiEvents\Values\MoneyValue;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class IdempotentRefundServiceTest extends TestCase
{
    private RefundAttemptRepositoryInterface&MockObject $attemptRepoMock;
    private RazorpayPaymentRefundService&MockObject $refundServiceMock;
    private ConnectionInterface&MockObject $dbConnectionMock;
    private IdempotentRefundService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attemptRepoMock = $this->createMock(RefundAttemptRepositoryInterface::class);
        $this->refundServiceMock = $this->createMock(RazorpayPaymentRefundService::class);
        $this->dbConnectionMock = $this->createMock(ConnectionInterface::class);

        $this->service = new IdempotentRefundService(
            $this->attemptRepoMock,
            $this->refundServiceMock,
            $this->dbConnectionMock
        );
    }

    public function testFirstAttemptCreatesAndReturnsRefund(): void
    {
        // Use RazorpayOrderDomainObject to match getPaymentType()
        $payment = $this->createMock(RazorpayOrderDomainObject::class);
        $payment->method('getId')->willReturn(1);
        $amount = MoneyValue::fromFloat(100, 'INR');
        $options = ['speed' => 'optimum'];
        $key = 'test-key';

        $this->dbConnectionMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn($callback) => $callback());

        $this->attemptRepoMock->expects($this->once())
            ->method('findByIdempotencyKey')
            ->with($key)
            ->willReturn(null);

        $attemptMock = $this->createMock(RefundAttemptDomainObject::class);
        $attemptMock->method('getId')->willReturn(1);

        $this->attemptRepoMock->expects($this->once())
            ->method('createAttempt')
            ->with($key, 1, 'razorpay', ['amount' => 10000, 'options' => $options])
            ->willReturn($attemptMock);

        $expectedResult = (object)['id' => 'refund_123'];
        $this->refundServiceMock->expects($this->once())
            ->method('refundPayment')
            ->with($payment, 10000, $key, $options)
            ->willReturn($expectedResult);

        $this->attemptRepoMock->expects($this->once())
            ->method('markSucceeded')
            ->with(1, (array)$expectedResult);

        $result = $this->service->refundWithIdempotency($payment, $amount, $options, $key);

        $this->assertSame($expectedResult, $result);
    }

    public function testReturnsStoredResponseIfAlreadySucceeded(): void
    {
        $payment = $this->createMock(RazorpayOrderDomainObject::class);
        $amount = MoneyValue::fromFloat(100, 'INR');
        $options = [];
        $key = 'test-key';

        $this->dbConnectionMock->method('transaction')->willReturnCallback(fn($c) => $c());

        $attempt = $this->createMock(RefundAttemptDomainObject::class);
        $attempt->method('getStatus')->willReturn('succeeded');
        $attempt->method('getResponseData')->willReturn(['id' => 'refund_123']);

        $this->attemptRepoMock->method('findByIdempotencyKey')->with($key)->willReturn($attempt);

        $this->refundServiceMock->expects($this->never())->method('refundPayment');

        $result = $this->service->refundWithIdempotency($payment, $amount, $options, $key);

        $this->assertEquals((object)['id' => 'refund_123'], $result);
    }

    public function testThrowsIfPreviousAttemptFailed(): void
    {
        $payment = $this->createMock(RazorpayOrderDomainObject::class);
        $amount = MoneyValue::fromFloat(100, 'INR');
        $options = [];
        $key = 'test-key';

        $this->dbConnectionMock->method('transaction')->willReturnCallback(fn($c) => $c());

        $attempt = $this->createMock(RefundAttemptDomainObject::class);
        $attempt->method('getStatus')->willReturn('failed');
        $attempt->method('getId')->willReturn(1);

        $this->attemptRepoMock->method('findByIdempotencyKey')->with($key)->willReturn($attempt);
        $this->attemptRepoMock->expects($this->once())->method('incrementAttempts')->with(1);

        $this->expectException(RefundNotPossibleException::class);
        $this->expectExceptionMessage('Previous refund attempt failed. Please retry.');

        $this->service->refundWithIdempotency($payment, $amount, $options, $key);
    }

    public function testThrowsIfAlreadyPending(): void
    {
        $payment = $this->createMock(RazorpayOrderDomainObject::class);
        $amount = MoneyValue::fromFloat(100, 'INR');
        $options = [];
        $key = 'test-key';

        $this->dbConnectionMock->method('transaction')->willReturnCallback(fn($c) => $c());

        $attempt = $this->createMock(RefundAttemptDomainObject::class);
        $attempt->method('getStatus')->willReturn('pending');

        $this->attemptRepoMock->method('findByIdempotencyKey')->with($key)->willReturn($attempt);

        $this->expectException(RefundNotPossibleException::class);
        $this->expectExceptionMessage('already being processed');

        $this->service->refundWithIdempotency($payment, $amount, $options, $key);
    }
}