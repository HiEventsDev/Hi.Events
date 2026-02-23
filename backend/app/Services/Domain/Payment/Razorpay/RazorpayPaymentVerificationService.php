<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\Exceptions\Razorpay\InvalidSignatureException;
use HiEvents\Services\Application\Handlers\Order\DTO\VerifyRazorpayPaymentDTO;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use Illuminate\Config\Repository;
use Psr\Log\LoggerInterface;

class RazorpayPaymentVerificationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Repository $config,
        private readonly RazorpayClientFactory $razorpayClientFactory,
    ) {}

    public function verifyPaymentSignature(VerifyRazorpayPaymentDTO $verifyRazorpayPaymentData): bool
    {
        $expectedSignature = hash_hmac(
            'sha256',
            $verifyRazorpayPaymentData->razorpay_order_id . '|' . $verifyRazorpayPaymentData->razorpay_payment_id,
            $this->config->get('services.razorpay.key_secret')
        );

        if ($expectedSignature !== $verifyRazorpayPaymentData->razorpay_signature) {
            $this->logger->error('Razorpay signature verification failed', [
                'expected' => $expectedSignature,
                'received' => $verifyRazorpayPaymentData->razorpay_signature,
                'order_id' => $verifyRazorpayPaymentData->razorpay_order_id,
                'payment_id' => $verifyRazorpayPaymentData->razorpay_payment_id,
            ]);

            throw new InvalidSignatureException();
        }

        return true;
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac(
            'sha256',
            $payload,
            $this->config->get('services.razorpay.webhook_secret')
        );

        return hash_equals($expectedSignature, $signature);
    }

    public function fetchPaymentDetails(string $paymentId): array
    {
        try {
            $razorpayClient = $this->razorpayClientFactory->create();
            $payment = $razorpayClient->payment->fetch($paymentId);
            
            return [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'order_id' => $payment->order_id,
                'method' => $payment->method,
                'created_at' => $payment->created_at,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch Razorpay payment details', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}