<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\Exceptions\Razorpay\InvalidSignatureException;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentVerificationService;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayPaymentCapturedHandler;
use HiEvents\Services\Domain\Payment\Razorpay\EventHandlers\RazorpayRefundHandler;
use Illuminate\Cache\Repository;
use Illuminate\Log\Logger;
use JsonException;
use Throwable;

class RazorpayWebhookHandler
{
    private static array $validEvents = [
        'payment.captured',
        'refund.processed',
        'payment.failed',
        'order.paid',
    ];

    public function __construct(
        private readonly RazorpayPaymentCapturedHandler $paymentCapturedHandler,
        private readonly RazorpayRefundHandler          $refundHandler,
        private readonly RazorpayPaymentVerificationService $razorpayPaymentService,
        private readonly Logger                         $logger,
        private readonly Repository                     $cache,
    ) {
    }

    /**
     * @throws InvalidSignatureException
     * @throws JsonException
     * @throws Throwable
     */
    public function handle(string $payload, string $signature): void
    {
        try {
            // Verify webhook signature
            if (!$this->razorpayPaymentService->verifyWebhookSignature($payload, $signature)) {
                throw new InvalidSignatureException(__('Invalid Razorpay webhook signature'));
            }

            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $event = $data['event'] ?? null;
            $eventId = $data['id'] ?? null;

            if (!$event || !$eventId) {
                $this->logger->error('Invalid Razorpay webhook payload', ['payload' => $payload]);
                return;
            }

            if (!in_array($event, self::$validEvents, true)) {
                $this->logger->debug(__('Received a :event Razorpay event, which has no handler', [
                    'event' => $event,
                ]), [
                    'event_id' => $eventId,
                    'event_type' => $event,
                ]);

                return;
            }

            if ($this->hasEventBeenHandled($eventId)) {
                $this->logger->debug('Razorpay webhook event already handled', [
                    'event_id' => $eventId,
                    'type' => $event,
                    'data' => $data,
                ]);

                return;
            }

            $this->logger->debug('Razorpay webhook received: ' . $event, $data);

            switch ($event) {
                case 'payment.captured':
                case 'order.paid':
                    $this->paymentCapturedHandler->handleEvent($data['payload']['payment']['entity']);
                    break;
                case 'refund.processed':
                    $this->refundHandler->handleEvent($data['payload']['refund']['entity']);
                    break;
                case 'payment.failed':
                    // Handle failed payments if needed
                    $this->logger->info('Razorpay payment failed', $data['payload']['payment']['entity']);
                    break;
            }

            $this->markEventAsHandled($eventId);
        } catch (InvalidSignatureException $exception) {
            $this->logger->error(
                'Unable to verify Razorpay webhook signature: ' . $exception->getMessage(), [
                    'payload' => $payload,
                ]
            );
            throw $exception;
        } catch (JsonException $exception) {
            $this->logger->error(
                'Invalid JSON in Razorpay webhook payload: ' . $exception->getMessage(), [
                    'payload' => $payload,
                ]
            );
            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->error('Unhandled Razorpay webhook error: ' . $exception->getMessage(), [
                'payload' => $payload,
            ]);
            throw $exception;
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