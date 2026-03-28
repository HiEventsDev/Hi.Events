<?php

namespace Tests\Unit\Services\Infrastructure\Razorpay;

use HiEvents\Services\Infrastructure\Razorpay\RazorpayApiClient;
use PHPUnit\Framework\MockObject\MockObject;
use Razorpay\Api\Api;
use Razorpay\Api\Order;
use Razorpay\Api\Payment;
use Razorpay\Api\Errors\BadRequestError;
use Tests\TestCase;

class RazorpayApiClientTest extends TestCase
{
    private Api&MockObject $apiMock;
    private RazorpayApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiMock = $this->createMock(Api::class);
        $this->client = new RazorpayApiClient('test_id', 'test_secret', $this->apiMock);
    }

    public function testItCanCreateAnOrderSuccessfully(): void
    {
        $orderData = ['amount' => 50000, 'currency' => 'INR', 'receipt' => 'receipt#1'];
        $expectedResponse = (object) ['id' => 'order_123', 'status' => 'created'];

        $orderMock = $this->createMock(Order::class);
        $orderMock->expects($this->once())
            ->method('create')
            ->with($orderData)
            ->willReturn($expectedResponse);

        $this->apiMock->order = $orderMock;

        $result = $this->client->createOrder($orderData);

        $this->assertEquals('order_123', $result->id);
        $this->assertEquals('created', $result->status);
    }

    public function testItThrowsExceptionWhenOrderCreationFailed(): void
    {
        $badOrderData = ['amount' => 0, 'currency' => 'INR'];
        $orderMock = $this->createMock(Order::class);

        $orderMock->expects($this->once())
            ->method('create')
            ->with($badOrderData)
            ->willThrowException(new BadRequestError('Order amount less than minimum amount allowed', 400, 400));

        $this->apiMock->order = $orderMock;

        $this->expectException(BadRequestError::class);
        $this->expectExceptionMessage('Order amount less than minimum amount allowed');

        $this->client->createOrder($badOrderData);
    }

    public function testItCanFetchAPaymentSuccessfully(): void
    {
        $paymentId = 'pay_123';
        $expectedResponse = (object) ['id' => 'pay_123', 'status' => 'captured', 'method' => 'upi'];

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->expects($this->once())
            ->method('fetch')
            ->with($paymentId)
            ->willReturn($expectedResponse);

        $this->apiMock->payment = $paymentMock;

        $result = $this->client->fetchPayment($paymentId);

        $this->assertEquals('pay_123', $result->id);
        $this->assertEquals('captured', $result->status);
        $this->assertEquals('upi', $result->method);
    }

    public function testItThrowsExceptionWhenPaymentIsInvalid(): void
    {
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->expects($this->once())
            ->method('fetch')
            ->willThrowException(new BadRequestError('Invalid ID', 400, 400));

        $this->apiMock->payment = $paymentMock;

        $this->expectException(BadRequestError::class);
        $this->expectExceptionMessage('Invalid ID');

        $this->client->fetchPayment('invalid_id');
    }

    public function testItCanRefundAPaymentSuccessfullyWithoutIdempotencyKey(): void
    {
        $paymentId = 'pay_123';
        $refundData = ['amount' => 5000];
        $expectedResponse = (object) ['id' => 'refund_123', 'status' => 'processed'];

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->expects($this->once())
            ->method('refund')
            ->with($refundData, null)
            ->willReturn($expectedResponse);

        $paymentMock->expects($this->once())
            ->method('fetch')
            ->with($paymentId)
            ->willReturn($paymentMock);

        $this->apiMock->payment = $paymentMock;

        $result = $this->client->refundPayment([
            'payment_id' => $paymentId,
            'amount' => 5000,
        ]);

        $this->assertEquals('refund_123', $result->id);
        $this->assertEquals('processed', $result->status);
    }

    public function testItCanRefundAPaymentSuccessfullyWithIdempotencyKey(): void
    {
        $paymentId = 'pay_123';
        $refundData = ['amount' => 5000, 'speed' => 'optimum', 'receipt' => 'receipt#1'];
        $idempotencyKey = 'idempotency-key-123';
        $expectedResponse = (object) ['id' => 'refund_123', 'status' => 'processed'];

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->expects($this->once())
            ->method('refund')
            ->with($refundData, $idempotencyKey)
            ->willReturn($expectedResponse);

        $paymentMock->expects($this->once())
            ->method('fetch')
            ->with($paymentId)
            ->willReturn($paymentMock);

        $this->apiMock->payment = $paymentMock;

        $result = $this->client->refundPayment([
            'payment_id' => $paymentId,
            'amount' => 5000,
            'speed' => 'optimum',
            'receipt' => 'receipt#1',
        ], $idempotencyKey);

        $this->assertEquals('refund_123', $result->id);
        $this->assertEquals('processed', $result->status);
    }

    public function testItThrowsExceptionWhenRefundFails(): void
    {
        $paymentId = 'pay_123';
        $refundData = ['amount' => 5000];

        $paymentMock = $this->createMock(Payment::class);

        $paymentMock->expects($this->once())
            ->method('refund')
            ->with($refundData, null)
            ->willThrowException(new BadRequestError('Refund failed', 400, 400));

        $paymentMock->expects($this->once())
            ->method('fetch')
            ->with($paymentId)
            ->willReturn($paymentMock);

        $this->apiMock->payment = $paymentMock;

        $this->expectException(BadRequestError::class);
        $this->expectExceptionMessage('Refund failed');

        $this->client->refundPayment([
            'payment_id' => $paymentId,
            'amount' => 5000,
        ]);
    }
}