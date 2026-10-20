<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderLookupService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalPaymentService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class CancelBoxOfficeCardActionPublicHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly BoxOfficeOrderLookupService $orderLookupService,
        private readonly StripeTerminalPaymentService $terminalPaymentService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws StripeClientConfigurationException
     */
    public function handle(BoxOfficeDomainObject $boxOffice, string $orderShortId, ?int $readerId): OrderDomainObject
    {
        $order = $this->orderLookupService->findOrFail($boxOffice, $orderShortId);

        $paymentIntentId = $order->getStripePayment()?->getPaymentIntentId();

        if ($readerId !== null && $paymentIntentId !== null) {
            $this->terminalPaymentService->cancelReaderActionFor($this->eventRepository->findById($order->getEventId()), $readerId, $paymentIntentId);
        }

        return $this->orderLookupService->findOrFail($boxOffice, $orderShortId);
    }
}
