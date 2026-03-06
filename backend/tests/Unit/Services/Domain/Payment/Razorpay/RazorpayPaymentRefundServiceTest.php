<?php

namespace Tests\Unit\Services\Domain\Payment\Razorpay;

use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentRefundService;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class RazorpayPaymentRefundServiceTest extends TestCase
{
    private RazorpayClientFactory&MockObject $clientFactoryMock;
    private RazorpayClientInterface&MockObject $clientMock;
    private RazorpayPaymentRefundService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientFactoryMock = $this->createMock(RazorpayClientFactory::class);
        $this->clientMock = $this->createMock(RazorpayClientInterface::class);
        $this->service = new RazorpayPaymentRefundService($this->clientFactoryMock);
    }

    public function testRefundPaymentSuccess(): void
    {
        $payment = $this->createMock(RazorpayOrderDomainObject::class);
        $payment->method('getRazorpayPaymentId')->willReturn('pay_abc123');

        $this->clientFactoryMock->method('create')->willReturn($this->clientMock);

        $this->clientMock->expects($this->once())
            ->method('refundPayment')
            ->with(
                ['payment_id' => 'pay_abc123', 'amount' => 10000],
                null
            )
            ->willReturn((object) ['id' => 'refund_xyz']);

        $result = $this->service->refundPayment($payment, 10000);

        $this->assertEquals('refund_xyz', $result->id);
    }

    public function testRefundPaymentThrowsWhenPaymentIdMissing(): void
    {
        $payment = $this->createMock(RazorpayOrderDomainObject::class);
        $payment->method('getRazorpayPaymentId')->willReturn(null);

        $this->expectException(RefundNotPossibleException::class);
        $this->expectExceptionMessage('No Razorpay payment ID found for this order.');

        $this->service->refundPayment($payment, 10000);
    }

    public function testRefundPaymentPassesIdempotencyKeyAndOptions(): void
    {
        $payment = $this->createMock(RazorpayOrderDomainObject::class);
        $payment->method('getRazorpayPaymentId')->willReturn('pay_abc123');

        $this->clientFactoryMock->method('create')->willReturn($this->clientMock);

        $this->clientMock->expects($this->once())
            ->method('refundPayment')
            ->with(
                [
                    'payment_id' => 'pay_abc123',
                    'amount' => 10000,
                    'speed' => 'optimum',
                    'receipt' => 'Receipt#123',
                    'notes' => ['reason' => 'Customer request']
                ],
                'idempotency-key-123'
            )
            ->willReturn((object) ['id' => 'refund_xyz']);

        $result = $this->service->refundPayment(
            $payment,
            10000,
            'idempotency-key-123',
            [
                'speed' => 'optimum',
                'receipt' => 'Receipt#123',
                'notes' => ['reason' => 'Customer request']
            ]
        );

        $this->assertEquals('refund_xyz', $result->id);
    }

    public function testRefundPaymentMergesOptionsCorrectly(): void
    {
        $payment = $this->createMock(RazorpayOrderDomainObject::class);
        $payment->method('getRazorpayPaymentId')->willReturn('pay_abc123');

        $this->clientFactoryMock->method('create')->willReturn($this->clientMock);

        $this->clientMock->expects($this->once())
            ->method('refundPayment')
            ->with(
                [
                    'payment_id' => 'pay_abc123',
                    'amount' => 5000,
                    'speed' => 'optimum',
                ],
                null
            )
            ->willReturn((object) ['id' => 'refund_xyz']);

        $result = $this->service->refundPayment(
            $payment,
            5000,
            null,
            ['speed' => 'optimum']
        );

        $this->assertEquals('refund_xyz', $result->id);
    }
}