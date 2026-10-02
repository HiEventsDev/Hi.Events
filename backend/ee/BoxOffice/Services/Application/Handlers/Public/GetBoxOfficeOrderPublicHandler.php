<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderLookupService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalPaymentService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Throwable;

class GetBoxOfficeOrderPublicHandler
{
    public function __construct(
        private readonly BoxOfficeOrderLookupService $orderLookupService,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly StripeTerminalPaymentService $terminalPaymentService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws StripeClientConfigurationException
     * @throws Throwable
     */
    public function handle(BoxOfficeDomainObject $boxOffice, string $orderShortId, ?int $readerId): OrderDomainObject
    {
        $order = $this->orderLookupService->findOrFail($boxOffice, $orderShortId);

        if ($order->getStripePayment() === null || ! $order->isOrderReserved()) {
            return $order;
        }

        return $this->terminalPaymentService->syncFromStripe(
            $order,
            $this->eventRepository->findById($order->getEventId()),
            $readerId,
        );
    }
}
