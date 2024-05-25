<?php

namespace HiEvents\Services\Handlers\Order\Payment\Stripe;

use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\AccountUpdateHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeRefundUpdatedHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentFailedHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Handlers\Order\Payment\Stripe\DTO\StripeWebhookDTO;
use Illuminate\Log\Logger;
use JsonException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

readonly class IncomingWebhookHandler
{
    public function __construct(
        private ChargeRefundUpdatedHandler    $refundEventHandlerService,
        private PaymentIntentSucceededHandler $paymentIntentSucceededHandler,
        private PaymentIntentFailedHandler    $paymentIntentFailedHandler,
        private AccountUpdateHandler          $accountUpdateHandler,
        private Logger                        $logger
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
            $event = Webhook::constructEvent(
                $webhookDTO->payload,
                $webhookDTO->headerSignature,
                config('services.stripe.webhook_secret'),
            );

            $this->logger->debug('Stripe event received', $event->data->object->toArray());

            switch ($event->type) {
                case Event::PAYMENT_INTENT_SUCCEEDED:
                    $this->paymentIntentSucceededHandler->handleEvent($event->data->object);
                    break;
                case Event::PAYMENT_INTENT_PAYMENT_FAILED:
                    $this->paymentIntentFailedHandler->handleEvent($event->data->object);
                    break;
                case Event::CHARGE_REFUND_UPDATED:
                    $this->refundEventHandlerService->handleEvent($event->data->object);
                    break;
                case Event::ACCOUNT_UPDATED:
                    $this->accountUpdateHandler->handleEvent($event->data->object);
                    break;
                default:
                    $this->logger->debug(sprintf('Unhandled Stripe webhook: %s', $event->type));
            }
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
}
