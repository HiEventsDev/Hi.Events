<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\Services\Infrastructure\Razorpay\RazorpayConfigurationService;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RazorpaySignatureVerificationService
{
    public function __construct(
        private readonly RazorpayConfigurationService $razorpayConfigurationService,
        private readonly LoggerInterface              $logger,
    ) {
    }

    /**
     * Verify the payment callback signature.
     *
     * Razorpay's expected format:
     *   HMAC-SHA256( razorpay_order_id + '|' + razorpay_payment_id, key_secret )
     */
    public function verifyPaymentSignature(
        string $razorpayOrderId,
        string $razorpayPaymentId,
        string $razorpaySignature,
    ): bool {
        $keySecret = $this->razorpayConfigurationService->getKeySecret();

        if (empty($keySecret)) {
            throw new RuntimeException('Razorpay key secret is not configured.');
        }

        $payload          = $razorpayOrderId . '|' . $razorpayPaymentId;
        $expectedSignature = hash_hmac('sha256', $payload, $keySecret);

        $valid = hash_equals($expectedSignature, $razorpaySignature);

        if (!$valid) {
            $this->logger->warning('Razorpay payment signature verification failed', [
                'razorpay_order_id'   => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);
        }

        return $valid;
    }

    /**
     * Verify the webhook signature.
     *
     * Razorpay's expected format:
     *   HMAC-SHA256( raw_body, webhook_secret )
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $webhookSecret = $this->razorpayConfigurationService->getWebhookSecret();

        if (empty($webhookSecret)) {
            throw new RuntimeException('Razorpay webhook secret is not configured.');
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);
        $valid             = hash_equals($expectedSignature, $signature);

        if (!$valid) {
            $this->logger->warning('Razorpay webhook signature verification failed');
        }

        return $valid;
    }
}
