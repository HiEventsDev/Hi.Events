<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\TerminalAttemptTracker;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use Stripe\Terminal\Reader;

class TerminalReaderActionFailedHandler
{
    public function __construct(
        private readonly StripePaymentsRepository $stripePaymentsRepository,
        private readonly TerminalAttemptTracker $attemptTracker,
    ) {}

    public function handleEvent(Reader $reader, ?int $eventCreatedAt = null): void
    {
        $action = $reader->action;
        $paymentIntentId = $action?->process_payment_intent?->payment_intent;

        if (! is_string($paymentIntentId) || $this->attemptTracker->predatesCurrentAttempt($paymentIntentId, $eventCreatedAt)) {
            return;
        }

        $this->stripePaymentsRepository->updateWhere(
            attributes: [
                StripePaymentDomainObjectAbstract::LAST_ERROR => [
                    'code' => $action->failure_code,
                    'message' => $action->failure_message,
                ],
            ],
            where: [
                StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntentId,
            ],
        );
    }
}
