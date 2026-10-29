<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\BoxOfficeSaleExpiredException;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalReaderUnavailableException;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderLookupService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalPaymentService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Throwable;

class StartBoxOfficeCardPaymentPublicHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly BoxOfficeOrderLookupService $orderLookupService,
        private readonly StripeTerminalPaymentService $terminalPaymentService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws BoxOfficeSaleExpiredException
     * @throws TerminalReaderUnavailableException
     * @throws CreatePaymentIntentFailedException
     * @throws StripeClientConfigurationException
     * @throws Throwable
     */
    public function handle(BoxOfficeDomainObject $boxOffice, string $orderShortId, ?int $readerId): OrderDomainObject
    {
        if ($readerId === null) {
            throw new ResourceConflictException(__('Choose a card reader when you start selling to take card payments'));
        }

        $order = $this->orderLookupService->findOrFail($boxOffice, $orderShortId);

        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($order->getEventId());

        return $this->terminalPaymentService->ensureProcessing($order, $event, $readerId);
    }
}
