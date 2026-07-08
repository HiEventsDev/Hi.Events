<?php

namespace Tests\Unit\Services\Domain\Payment\Razorpay;

use Exception;
use HiEvents\Exceptions\Razorpay\InvalidSignatureException;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\VerifyRazorpayPaymentDTO;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentVerificationService;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use Illuminate\Config\Repository;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class RazorpayPaymentVerificationServiceTest extends TestCase
{
    private LoggerInterface&MockObject $loggerMock;
    private Repository&MockObject $configMock;
    private RazorpayClientFactory&MockObject $clientFactoryMock;
    private RazorpayPaymentVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->configMock = $this->createMock(Repository::class);
        $this->clientFactoryMock = $this->createMock(RazorpayClientFactory::class);

        $this->service = new RazorpayPaymentVerificationService(
            $this->loggerMock,
            $this->configMock,
            $this->clientFactoryMock
        );
    }

    public function testVerifyPaymentSignatureReturnsTrueOnSuccess(): void
    {
        $orderId = 'order_123';
        $paymentId = 'pay_123';
        $secret = 'test_secret';
        $signature = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);

        $this->configMock->method('get')
            ->with('services.razorpay.key_secret')
            ->willReturn($secret);

        $dto = new VerifyRazorpayPaymentDTO(
            razorpay_order_id: $orderId,
            razorpay_payment_id: $paymentId,
            razorpay_signature: $signature
        );

        $result = $this->service->verifyPaymentSignature($dto);

        $this->assertTrue($result);
    }

    public function testVerifyPaymentSignatureThrowsExceptionOnFailure(): void
    {
        $this->configMock->method('get')
            ->with('services.razorpay.key_secret')
            ->willReturn('test_secret');

        $dto = new VerifyRazorpayPaymentDTO(
            razorpay_order_id: 'order_123',
            razorpay_payment_id: 'pay_123',
            razorpay_signature: 'invalid_signature'
        );

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Razorpay signature verification failed', $this->isType('array'));

        $this->expectException(InvalidSignatureException::class);

        $this->service->verifyPaymentSignature($dto);
    }

    public function testVerifyWebhookSignatureReturnsTrueOnMatch(): void
    {
        $payload = '{"event":"payment.captured"}';
        $secret = 'webhook_secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->configMock->method('get')
            ->with('services.razorpay.webhook_secret')
            ->willReturn($secret);

        $result = $this->service->verifyWebhookSignature($payload, $signature);

        $this->assertTrue($result);
    }

    public function testVerifyWebhookSignatureReturnsFalseOnMismatch(): void
    {
        $this->configMock->method('get')
            ->with('services.razorpay.webhook_secret')
            ->willReturn('secret');

        $result = $this->service->verifyWebhookSignature('payload', 'wrong_signature');

        $this->assertFalse($result);
    }

    public function testFetchPaymentDetailsReturnsMappedArray(): void
    {
        $paymentId = 'pay_123';
        $paymentData = (object) [
            'id' => $paymentId,
            'amount' => 50000,
            'currency' => 'INR',
            'status' => 'captured',
            'order_id' => 'order_123',
            'method' => 'card',
            'created_at' => 123456789
        ];

        $clientMock = $this->createMock(\HiEvents\Services\Infrastructure\Razorpay\RazorpayClientInterface::class);

        $clientMock->expects($this->once())
            ->method('fetchPayment')
            ->with($paymentId)
            ->willReturn($paymentData);

        $this->clientFactoryMock->method('create')->willReturn($clientMock);

        $result = $this->service->fetchPaymentDetails($paymentId);

        $this->assertEquals([
            'id' => $paymentId,
            'amount' => 50000,
            'currency' => 'INR',
            'status' => 'captured',
            'order_id' => 'order_123',
            'method' => 'card',
            'created_at' => 123456789
        ], $result);
    }

    public function testFetchPaymentDetailsLogsAndThrowsExceptionOnFailure(): void
    {
        $this->clientFactoryMock->method('create')
            ->willThrowException(new Exception('API Error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Failed to fetch Razorpay payment details', $this->isType('array'));

        $this->expectException(Exception::class);

        $this->service->fetchPaymentDetails('pay_123');
    }
}