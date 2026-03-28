<?php

namespace Tests\Unit\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\Exceptions\Razorpay\InvalidSignatureException;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\RazorpayWebhookHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayOrderPaidHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayPaymentAuthorizedHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayPaymentCapturedHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayPaymentFailedHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayRefundHandler;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentVerificationService;
use Illuminate\Cache\Repository;
use Illuminate\Log\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class RazorpayWebhookHandlerTest extends TestCase
{
    private RazorpayPaymentCapturedHandler&MockObject $paymentCapturedHandlerMock;
    private RazorpayOrderPaidHandler&MockObject $orderPaidHandlerMock;
    private RazorpayRefundHandler&MockObject $refundHandlerMock;
    private RazorpayPaymentFailedHandler&MockObject $paymentFailedHandlerMock;
    private RazorpayPaymentAuthorizedHandler&MockObject $paymentAuthorizedHandlerMock;
    private RazorpayPaymentVerificationService&MockObject $verificationServiceMock;
    private Logger&MockObject $loggerMock;
    private Repository&MockObject $cacheMock;
    private RazorpayWebhookHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentCapturedHandlerMock = $this->createMock(RazorpayPaymentCapturedHandler::class);
        $this->orderPaidHandlerMock = $this->createMock(RazorpayOrderPaidHandler::class);
        $this->refundHandlerMock = $this->createMock(RazorpayRefundHandler::class);
        $this->paymentFailedHandlerMock = $this->createMock(RazorpayPaymentFailedHandler::class);
        $this->paymentAuthorizedHandlerMock = $this->createMock(RazorpayPaymentAuthorizedHandler::class);
        $this->verificationServiceMock = $this->createMock(RazorpayPaymentVerificationService::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->cacheMock = $this->createMock(Repository::class);

        $this->handler = new RazorpayWebhookHandler(
            $this->paymentCapturedHandlerMock,
            $this->orderPaidHandlerMock,
            $this->refundHandlerMock,
            $this->paymentFailedHandlerMock,
            $this->paymentAuthorizedHandlerMock,
            $this->verificationServiceMock,
            $this->loggerMock,
            $this->cacheMock
        );
    }

    public function testHandleThrowsExceptionOnInvalidSignature(): void
    {
        $payload = '{"test":"data"}';
        $signature = 'invalid_sig';

        $this->verificationServiceMock->method('verifyWebhookSignature')
            ->with($payload, $signature)
            ->willReturn(false);

        $this->expectException(InvalidSignatureException::class);

        $this->handler->handle($payload, $signature);
    }

    public function testHandleReturnsEarlyIfEventAlreadyHandled(): void
    {
        $payload = $this->createPaymentWebhookJson('payment.captured', 'pay_123');
        $signature = 'valid_sig';

        $this->verificationServiceMock->method('verifyWebhookSignature')->willReturn(true);
        
        $this->cacheMock->expects($this->once())
            ->method('has')
            ->with('razorpay_webhook_pay_123')
            ->willReturn(true);

        $this->paymentCapturedHandlerMock->expects($this->never())->method('handleEvent');

        $this->handler->handle($payload, $signature);
    }

    public function testHandleRoutesToPaymentCapturedHandler(): void
    {
        $payload = $this->createPaymentWebhookJson('payment.captured', 'pay_123');
        $signature = 'valid_sig';

        $this->verificationServiceMock->method('verifyWebhookSignature')->willReturn(true);
        $this->cacheMock->method('has')->willReturn(false);

        $this->paymentCapturedHandlerMock->expects($this->once())->method('handleEvent');
        $this->cacheMock->expects($this->once())->method('put')->with('razorpay_webhook_pay_123', true);

        $this->handler->handle($payload, $signature);
    }

    public function testHandleRoutesToOrderPaidHandler(): void
    {
        $payload = json_encode([
            'entity' => 'event',
            'account_id' => 'acc_123',
            'event' => 'order.paid',
            'created_at' => time(),
            'payload' => [
                'order' => [
                    'entity' => [
                        'id' => 'order_rzp_123',
                        'entity' => 'order',
                        'amount' => 50000,
                        'amount_paid' => 50000,
                        'amount_due' => 0,
                        'currency' => 'INR',
                        'status' => 'paid',
                        'receipt' => 'rcpt_1',
                        'notes' => [],
                        'created_at' => time()
                    ]
                ],
                'payment' => [
                    'entity' => $this->getPaymentEntityData('pay_123')
                ]
            ]
        ]);
        $signature = 'valid_sig';

        $this->verificationServiceMock->method('verifyWebhookSignature')->willReturn(true);
        $this->cacheMock->method('has')->willReturn(false);

        $this->orderPaidHandlerMock->expects($this->once())->method('handleEvent');
        $this->cacheMock->expects($this->once())->method('put')->with('razorpay_webhook_order_rzp_123', true);

        $this->handler->handle($payload, $signature);
    }

    public function testHandleReturnsEarlyForUnknownEvent(): void
    {
        $payload = json_encode([
            'entity' => 'event',
            'account_id' => 'acc_123',
            'event' => 'payment.dispute.created',
            'created_at' => time(),
            'payload' => []
        ]);
        $signature = 'valid_sig';

        $this->verificationServiceMock->method('verifyWebhookSignature')->willReturn(true);

        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with('Unsupported or unknown webhook event', $this->isType('array'));

        $this->handler->handle($payload, $signature);
    }

    private function createPaymentWebhookJson(string $event, string $paymentId): string
    {
        return json_encode([
            'entity' => 'event',
            'account_id' => 'acc_123',
            'event' => $event,
            'created_at' => time(),
            'payload' => [
                'payment' => [
                    'entity' => $this->getPaymentEntityData($paymentId)
                ]
            ]
        ]);
    }

    private function getPaymentEntityData(string $paymentId): array
    {
        return [
            'id' => $paymentId,
            'entity' => 'payment',
            'amount' => 50000,
            'currency' => 'INR',
            'status' => 'captured',
            'method' => 'card',
            'order_id' => 'order_rzp_123',
            'fee' => 100,
            'tax' => 18,
            'description' => 'Test',
            'notes' => [],
            'vpa' => null,
            'email' => 'test@example.com',
            'contact' => '+919999999999',
            'created_at' => time(),
            'error' => null
        ];
    }
}