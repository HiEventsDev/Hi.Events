<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayWebhookEnvelope;
use HiEvents\Exceptions\Razorpay\InvalidSignatureException;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentVerificationService;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayPaymentCapturedHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayOrderPaidHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayRefundHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayPaymentFailedHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayPaymentAuthorizedHandler;
use Illuminate\Cache\Repository;
use Illuminate\Log\Logger;
use JsonException;
use Throwable;
use Spatie\LaravelData\Exceptions\CannotCreateData;

class RazorpayWebhookHandler
{
    private static array $validEvents = [
        'payment.captured',
        'order.paid',
        'refund.processed',
        'payment.failed',
        'payment.authorized',
    ];

    public function __construct(
        private readonly RazorpayPaymentCapturedHandler $paymentCapturedHandler,
        private readonly RazorpayOrderPaidHandler $orderPaidHandler,
        private readonly RazorpayRefundHandler $refundHandler,
        private readonly RazorpayPaymentFailedHandler $paymentFailedHandler,
        private readonly RazorpayPaymentAuthorizedHandler $paymentAuthorizedHandler,
        private readonly RazorpayPaymentVerificationService $razorpayPaymentService,
        private readonly Logger $logger,
        private readonly Repository $cache,
    ) {
    }

    /**
     * @throws InvalidSignatureException
     * @throws JsonException
     * @throws CannotCreateData
     * @throws Throwable
     */
    public function handle(string $payload, string $signature): void
    {
        try {
            // 1. Verify webhook signature
            if (!$this->razorpayPaymentService->verifyWebhookSignature($payload, $signature)) {
                throw new InvalidSignatureException(__('Invalid Razorpay webhook signature'));
            }

            // 2. Decode JSON and create envelope DTO
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            try {
                $envelope = RazorpayWebhookEnvelope::fromArray($data);
            } catch (\InvalidArgumentException $e) {
                $this->logger->debug('Unsupported or unknown webhook event', [
                    'event' => $data['event'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                return;
            }
            $event = $envelope->event;

            // 3. Validate event type
            if (!in_array($event, self::$validEvents, true)) {
                $this->logger->debug('Unsupported webhook event', ['event' => $event]);
                return;
            }

            // 4. Extract unique event ID for idempotency
            $eventId = match ($event) {
                'payment.captured', 'payment.failed', 'payment.authorized' => $envelope->payload->payment->id,
                'order.paid' => $envelope->payload->order->id,
                'refund.processed' => $envelope->payload->refund->id,
                default => null,
            };

            if (!$eventId) {
                $this->logger->error('Could not extract event ID from payload', ['event' => $event]);
                return;
            }

            // 5. Idempotency check
            if ($this->hasEventBeenHandled($eventId)) {
                $this->logger->debug('Razorpay webhook event already handled', [
                    'event_id' => $eventId,
                    'type' => $event,
                ]);
                return;
            }

            $this->logger->debug('Processing Razorpay webhook', [
                'event' => $event,
                'event_id' => $eventId,
            ]);

            // 6. Route to the appropriate handler based on event type
            match ($event) {
                'payment.captured' => $this->paymentCapturedHandler->handleEvent($envelope->payload),
                'order.paid' => $this->orderPaidHandler->handleEvent($envelope->payload),
                'refund.processed' => $this->refundHandler->handleEvent($envelope->payload),
                'payment.failed' => $this->paymentFailedHandler->handleEvent($envelope->payload),
                'payment.authorized' => $this->paymentAuthorizedHandler->handleEvent($envelope->payload),
                default => $this->logger->debug('No handler for event', ['event' => $event]),
            };

            // 7. Mark event as handled
            $this->markEventAsHandled($eventId);
        } catch (InvalidSignatureException $e) {
            $this->logger->error('Signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (JsonException $e) {
            $this->logger->error('Invalid JSON payload', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        } catch (CannotCreateData $e) {
            $this->logger->error('Failed to create DTO from webhook payload', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Unhandled exception processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function hasEventBeenHandled(string $eventId): bool
    {
        return $this->cache->has('razorpay_webhook_' . $eventId);
    }

    private function markEventAsHandled(string $eventId): void
    {
        $this->logger->info('Marking Razorpay webhook event as handled', [
            'event_id' => $eventId,
        ]);
        $this->cache->put('razorpay_webhook_' . $eventId, true, now()->addMinutes(60));
    }
}