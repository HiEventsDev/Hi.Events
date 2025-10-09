<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Stripe;

use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\DTO\StripeWebhookDTO;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\AccountUpdateHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeRefundUpdatedHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentFailedHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use Illuminate\Cache\Repository;
use Illuminate\Log\Logger;
use JsonException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;

class IncomingWebhookHandler
{
    private static array $validEvents = [
        Event::PAYMENT_INTENT_SUCCEEDED,
        Event::PAYMENT_INTENT_PAYMENT_FAILED,
        Event::ACCOUNT_UPDATED,
        Event::REFUND_UPDATED,
    ];

    public function __construct(
        private readonly ChargeRefundUpdatedHandler    $refundEventHandlerService,
        private readonly PaymentIntentSucceededHandler $paymentIntentSucceededHandler,
        private readonly PaymentIntentFailedHandler    $paymentIntentFailedHandler,
        private readonly AccountUpdateHandler          $accountUpdateHandler,
        private readonly Logger                        $logger,
        private readonly Repository                    $cache,
        private readonly StripeConfigurationService    $stripeConfigurationService,
    )
    {
    }

    /**
     * @throws SignatureVerificationException
     * @throws JsonException
     * @throws Throwable
     */
    public function handle(StripeWebhookDTO $webhookDTO): void
    {
        try {
            $event = $this->constructEventWithValidPlatform($webhookDTO);

            if (!in_array($event->type, self::$validEvents, true)) {
                $this->logger->debug(__('Received a :event Stripe event, which has no handler', [
                    'event' => $event->type,
                ]), [
                    'event_id' => $event->id,
                    'event_type' => $event->type,
                ]);

                return;
            }

            if ($this->hasEventBeenHandled($event)) {
                $this->logger->debug('Stripe event already handled', [
                    'event_id' => $event->id,
                    'type' => $event->type,
                    'data' => $event->data->object->toArray(),
                ]);

                return;
            }

            $this->logger->debug('Stripe event received', $event->data->object->toArray());

            switch ($event->type) {
                case Event::PAYMENT_INTENT_SUCCEEDED:
                    $this->paymentIntentSucceededHandler->handleEvent($event->data->object);
                    break;
                case Event::PAYMENT_INTENT_PAYMENT_FAILED:
                    $this->paymentIntentFailedHandler->handleEvent($event->data->object);
                    break;
                case Event::REFUND_UPDATED:
                    $this->refundEventHandlerService->handleEvent($event->data->object);
                    break;
                case Event::ACCOUNT_UPDATED:
                    $this->accountUpdateHandler->handleEvent($event->data->object);
                    break;
            }

            $this->markEventAsHandled($event);
        } catch (CannotAcceptPaymentException $exception) {
            $this->logger->error(
                'Cannot accept payment: ' . $exception->getMessage(), [
                    'payload' => $webhookDTO->payload,
                ]
            );
            throw $exception;
        } catch (SignatureVerificationException $exception) {
            $this->logger->error(
                'Unable to verify Stripe signature: ' . $exception->getMessage(), [
                    'payload' => $webhookDTO->payload,
                ]
            );
            throw $exception;
        } catch (UnexpectedValueException $exception) {
            $this->logger->error(
                'Unexpected value in Stripe payload: ' . $exception->getMessage(), [
                    'payload' => $webhookDTO->payload,
                ]
            );
            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->error('Unhandled Stripe error: ' . $exception->getMessage(), [
                'payload' => $webhookDTO->payload,
            ]);
            throw $exception;
        }
    }

    private function constructEventWithValidPlatform(StripeWebhookDTO $webhookDTO): Event
    {
        $webhookSecrets = $this->stripeConfigurationService->getAllWebhookSecrets();
        $lastException = null;

        foreach ($webhookSecrets as $platform => $webhookSecret) {
            try {
                if (!$webhookSecret) {
                    continue;
                }

                $event = Webhook::constructEvent(
                    $webhookDTO->payload,
                    $webhookDTO->headerSignature,
                    $webhookSecret
                );

                $this->logger->debug('Webhook validated with platform: ' . $platform, [
                    'event_id' => $event->id,
                    'platform' => $platform,
                ]);

                return $event;
            } catch (SignatureVerificationException $exception) {
                $lastException = $exception;
                continue;
            }
        }

        throw $lastException ?? new SignatureVerificationException(__('Unable to verify Stripe signature with any platform'));
    }

    private function hasEventBeenHandled(Event $event): bool
    {
        return $this->cache->has('stripe_event_' . $event->id);
    }

    private function markEventAsHandled(Event $event): void
    {
        $this->logger->info('Marking Stripe event as handled', [
            'event_id' => $event->id,
            'type' => $event->type,
        ]);
        $this->cache->put('stripe_event_' . $event->id, true, now()->addMinutes(60));
    }
}
