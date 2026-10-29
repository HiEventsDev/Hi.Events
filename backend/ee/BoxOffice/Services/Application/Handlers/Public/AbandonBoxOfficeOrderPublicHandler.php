<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderLookupService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalPaymentService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class AbandonBoxOfficeOrderPublicHandler
{
    public function __construct(
        private readonly BoxOfficeOrderLookupService $orderLookupService,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly StripeTerminalPaymentService $terminalPaymentService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly TransactionLockService $transactionLockService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function handle(BoxOfficeDomainObject $boxOffice, string $orderShortId, ?int $readerId): OrderDomainObject
    {
        $order = $this->orderLookupService->findOrFail($boxOffice, $orderShortId);

        $this->assertInProgress($order);

        $this->terminalPaymentService->releaseCardPayment(
            $order,
            $this->eventRepository->findById($order->getEventId()),
            $readerId,
        );

        $this->databaseManager->transaction(function () use ($boxOffice, $orderShortId) {
            $this->transactionLockService->lockOrder($orderShortId);

            $order = $this->orderLookupService->findOrFail($boxOffice, $orderShortId);
            $this->assertInProgress($order);

            $this->orderRepository->updateFromArray($order->getId(), [
                OrderDomainObjectAbstract::STATUS => OrderStatus::ABANDONED->name,
            ]);
            $this->attendeeRepository->updateWhere(
                attributes: ['status' => AttendeeStatus::CANCELLED->name],
                where: ['order_id' => $order->getId()],
            );
        });

        return $this->orderLookupService->findOrFail($boxOffice, $orderShortId);
    }

    /**
     * @throws ResourceConflictException
     */
    private function assertInProgress(OrderDomainObject $order): void
    {
        if (! $order->isOrderReserved()) {
            throw new ResourceConflictException(__('Only a sale in progress can be abandoned'));
        }
    }
}
