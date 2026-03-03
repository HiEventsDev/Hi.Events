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
        // 1. Arrange: Send bad data (e.g., amount is 0, which Razorpay rejects)
        $badOrderData = ['amount' => 0, 'currency' => 'INR'];

        $orderMock = $this->createMock(Order::class);

        // Tell the mock to throw a Razorpay BadRequestError when 'create' is called
        $orderMock->expects($this->once())
            ->method('create')
            ->with($badOrderData)
            ->willThrowException(new BadRequestError('Order amount less than minimum amount allowed', 400, 400));

        $this->apiMock->order = $orderMock;

        // 2. Assert: Tell PHPUnit to expect this exact error to crash the test
        $this->expectException(BadRequestError::class);
        $this->expectExceptionMessage('Order amount less than minimum amount allowed');

        // 3. Act: Trigger the error by calling your client
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
}