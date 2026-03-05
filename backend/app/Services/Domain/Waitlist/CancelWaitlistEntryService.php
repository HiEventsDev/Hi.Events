<?php

namespace HiEvents\Services\Domain\Waitlist;

use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class CancelWaitlistEntryService
{
    public function __construct(
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
        private readonly OrderRepositoryInterface         $orderRepository,
        private readonly DatabaseManager                  $databaseManager,
        private readonly ProductPriceRepositoryInterface  $productPriceRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     */
    public function cancelByToken(string $cancelToken, ?int $eventId = null): WaitlistEntryDomainObject
    {
        $conditions = ['cancel_token' => $cancelToken];

        if ($eventId !== null) {
            $conditions['event_id'] = $eventId;
        }

        $entry = $this->waitlistEntryRepository->findFirstWhere($conditions);

        if ($entry === null) {
            throw new ResourceNotFoundException(__('Waitlist entry not found'));
        }

        return $this->cancelEntry($entry);
    }

    /**
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     */
    public function cancelById(int $entryId, int $eventId): WaitlistEntryDomainObject
    {
        $entry = $this->waitlistEntryRepository->findFirstWhere([
            'id' => $entryId,
            'event_id' => $eventId,
        ]);

        if ($entry === null) {
            throw new ResourceNotFoundException(__('Waitlist entry not found'));
        }

        return $this->cancelEntry($entry);
    }

    /**
     * @throws ResourceConflictException
     */
    private function cancelEntry(WaitlistEntryDomainObject $entry): WaitlistEntryDomainObject
    {
        if (!in_array($entry->getStatus(), [
            WaitlistEntryStatus::WAITING->name,
            WaitlistEntryStatus::OFFERED->name,
        ], true)) {
            throw new ResourceConflictException(__('This waitlist entry cannot be cancelled'));
        }

        return $this->databaseManager->transaction(function () use ($entry) {
            $wasOffered = $entry->getStatus() === WaitlistEntryStatus::OFFERED->name;

            if ($entry->getOrderId() !== null) {
                $this->orderRepository->deleteWhere([
                    'id' => $entry->getOrderId(),
                    'status' => OrderStatus::RESERVED->name,
                ]);
            }

            $this->waitlistEntryRepository->updateWhere(
                attributes: [
                    'status' => WaitlistEntryStatus::CANCELLED->name,
                    'cancelled_at' => now(),
                    'order_id' => null,
                ],
                where: ['id' => $entry->getId()],
            );

            if ($wasOffered) {
                $productPrice = $this->productPriceRepository->findById($entry->getProductPriceId());

                event(new CapacityChangedEvent(
                    eventId: $entry->getEventId(),
                    direction: CapacityChangeDirection::INCREASED,
                    productId: $productPrice->getProductId(),
                    productPriceId: $entry->getProductPriceId(),
                ));
            }

            return $this->waitlistEntryRepository->findById($entry->getId());
        });
    }
}
