<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\PaymentCapturedHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\PaymentFailedHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RefundProcessedHandler;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpaySignatureVerificationService;
use Illuminate\Cache\Repository;
use Psr\Log\LoggerInterface;
use Throwable;

class IncomingWebhookHandler
{
    public function __construct(
        private readonly RazorpaySignatureVerificationService $signatureVerificationService,
        private readonly PaymentCapturedHandler               $paymentCapturedHandler,
        private readonly PaymentFailedHandler                 $paymentFailedHandler,
        private readonly RefundProcessedHandler               $refundProcessedHandler,
        private readonly LoggerInterface                      $logger,
        private readonly Repository                           $cache,
    ) {
    }

    /**
     * Handle incoming Razorpay webhook.
     *
     * @throws UnauthorizedException|Throwable
     */
    public function handle(string $rawBody, string $signature, array $payload): void
    {
        if (!$this->signatureVerificationService->verifyWebhookSignature($rawBody, $signature)) {
            throw new UnauthorizedException('Invalid Razorpay webhook signature');
        }

        $eventId = $payload['event'] ?? null;
        if (!$eventId) {
            $this->logger->warning('Razorpay webhook missing event type', ['payload' => $payload]);
            return;
        }

        // Razorpay sends `x-razorpay-event-id` in headers usually, but for idempotency,
        // we can use a combination of the event ID or generate a hash of the payload if needed.
        // Assuming we rely on the webhook header ID from the HTTP Action later, but here
        // we'll extract standard fields.
        $webhookId = $payload['event_id'] ?? md5($rawBody);
        
        $cacheKey = 'razorpay_webhook_handled_' . $webhookId;

        if ($this->cache->has($cacheKey)) {
            $this->logger->info('Razorpay webhook already handled', ['webhook_id' => $webhookId]);
            return;
        }

        $this->logger->info('Processing Razorpay webhook', [
            'event'      => $eventId,
            'webhook_id' => $webhookId,
        ]);

        switch ($eventId) {
            case 'payment.captured':
                $this->paymentCapturedHandler->handle($payload);
                break;
            case 'payment.failed':
                $this->paymentFailedHandler->handle($payload);
                break;
            case 'refund.processed':
                $this->refundProcessedHandler->handle($payload);
                break;
            default:
                $this->logger->debug('Unhandled Razorpay webhook event', ['event' => $eventId]);
        }

        $this->cache->put($cacheKey, true, 3600);
    }
}
