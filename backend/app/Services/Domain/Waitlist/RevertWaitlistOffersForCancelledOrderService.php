<?php

namespace HiEvents\Services\Domain\Waitlist;

use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;

class RevertWaitlistOffersForCancelledOrderService
{
    public function __construct(
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
        private readonly ProductPriceRepositoryInterface $productPriceRepository,
    ) {}

    /**
     * @return array<int, CapacityChangedEvent>
     */
    public function revertOffersForOrder(int $orderId): array
    {
        $capacityEvents = [];

        $entries = $this->waitlistEntryRepository->findWhere([
            'order_id' => $orderId,
            ['status', 'in', [WaitlistEntryStatus::OFFERED->name]],
        ]);

        foreach ($entries as $entry) {
            $this->waitlistEntryRepository->updateWhere(
                attributes: [
                    'status' => WaitlistEntryStatus::WAITING->name,
                    'order_id' => null,
                    'offered_at' => null,
                    'offer_expires_at' => null,
                    'offer_token' => null,
                ],
                where: [
                    'id' => $entry->getId(),
                    'status' => WaitlistEntryStatus::OFFERED->name,
                ],
            );

            $productPrice = $this->productPriceRepository->findById($entry->getProductPriceId());
            $capacityEvents[] = new CapacityChangedEvent(
                eventId: $entry->getEventId(),
                direction: CapacityChangeDirection::INCREASED,
                productId: $productPrice->getProductId(),
                productPriceId: $entry->getProductPriceId(),
                eventOccurrenceId: $entry->getEventOccurrenceId(),
            );
        }

        return $capacityEvents;
    }
}
