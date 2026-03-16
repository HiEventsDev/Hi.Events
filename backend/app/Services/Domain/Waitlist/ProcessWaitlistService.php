<?php

namespace HiEvents\Services\Domain\Waitlist;

use HiEvents\Constants;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Exceptions\NoCapacityAvailableException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Waitlist\SendWaitlistOfferEmailJob;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProcessWaitlistService
{
    private const DEFAULT_OFFER_TIMEOUT_MINUTES = 60 * 12; // 12 hours

    public function __construct(
        private readonly WaitlistEntryRepositoryInterface       $waitlistEntryRepository,
        private readonly DatabaseManager                        $databaseManager,
        private readonly OrderManagementService                 $orderManagementService,
        private readonly OrderItemProcessingService             $orderItemProcessingService,
        private readonly ProductRepositoryInterface             $productRepository,
        private readonly AvailableProductQuantitiesFetchService $availableQuantitiesService,
        private readonly ProductPriceRepositoryInterface        $productPriceRepository,
    )
    {
    }

    /**
     * @return Collection<int, WaitlistEntryDomainObject>
     */
    public function offerToNext(
        int                      $productPriceId,
        int                      $quantity,
        EventDomainObject        $event,
        EventSettingDomainObject $eventSettings,
    ): Collection
    {
        return $this->databaseManager->transaction(function () use ($productPriceId, $quantity, $event, $eventSettings) {
            $this->databaseManager->statement('SELECT pg_advisory_xact_lock(?)', [$event->getId()]);
            $this->waitlistEntryRepository->lockForProductPrice($productPriceId);

            $quantities = $this->availableQuantitiesService->getAvailableProductQuantities(
                $event->getId(),
                ignoreCache: true,
            );

            $availableCount = $this->getAvailableCountForPrice($quantities, $productPriceId);

            if ($availableCount <= 0) {
                throw new NoCapacityAvailableException(
                    __('No capacity available. Available: :available', [
                        'available' => $availableCount,
                    ])
                );
            }

            $toOffer = min($quantity, $availableCount);
            $entries = $this->waitlistEntryRepository->getNextWaitingEntries($productPriceId, $toOffer);

            if ($entries->isEmpty()) {
                throw new NoCapacityAvailableException(
                    __('There are no waiting entries for this product')
                );
            }

            $offeredEntries = collect();

            foreach ($entries as $entry) {
                $updatedEntry = $this->offerEntry($entry, $event, $eventSettings);
                $offeredEntries->push($updatedEntry);
            }

            return $offeredEntries;
        });
    }

    /**
     * @return Collection<int, WaitlistEntryDomainObject>
     */
    public function offerSpecificEntry(
        int                      $entryId,
        int                      $eventId,
        EventDomainObject        $event,
        EventSettingDomainObject $eventSettings,
    ): Collection
    {
        return $this->databaseManager->transaction(function () use ($entryId, $eventId, $event, $eventSettings) {
            $this->databaseManager->statement('SELECT pg_advisory_xact_lock(?)', [$event->getId()]);

            /** @var WaitlistEntryDomainObject|null $entry */
            $entry = $this->waitlistEntryRepository->findFirstWhere([
                'id' => $entryId,
                'event_id' => $eventId,
            ]);

            if ($entry === null) {
                throw new ResourceNotFoundException(__('Waitlist entry not found'));
            }

            $validStatuses = [WaitlistEntryStatus::WAITING->name, WaitlistEntryStatus::OFFER_EXPIRED->name];
            if (!in_array($entry->getStatus(), $validStatuses, true)) {
                throw new ResourceConflictException(
                    __('This waitlist entry cannot be offered in its current status')
                );
            }

            $this->waitlistEntryRepository->lockForProductPrice($entry->getProductPriceId());

            $quantities = $this->availableQuantitiesService->getAvailableProductQuantities(
                $event->getId(),
                ignoreCache: true,
            );

            $availableCount = $this->getAvailableCountForPrice($quantities, $entry->getProductPriceId());

            if ($availableCount <= 0) {
                throw new NoCapacityAvailableException(
                    __('No capacity available to offer this waitlist entry. You will need to increase the available quantity for the product. Available: :available', [
                        'available' => $availableCount,
                    ])
                );
            }

            $updatedEntry = $this->offerEntry($entry, $event, $eventSettings);

            return collect([$updatedEntry]);
        });
    }

    private function offerEntry(
        WaitlistEntryDomainObject $entry,
        EventDomainObject         $event,
        EventSettingDomainObject  $eventSettings,
    ): WaitlistEntryDomainObject
    {
        $offerExpiresAt = $this->calculateOfferExpiry($eventSettings);
        $sessionIdentifier = sha1(Str::uuid() . Str::random(40));
        $order = $this->createReservedOrder($entry, $event, $eventSettings, $sessionIdentifier);

        $this->waitlistEntryRepository->updateWhere(
            attributes: [
                'status' => WaitlistEntryStatus::OFFERED->name,
                'offer_token' => Str::random(64),
                'offered_at' => now(),
                'offer_expires_at' => $offerExpiresAt,
                'order_id' => $order->getId(),
            ],
            where: ['id' => $entry->getId()],
        );

        /** @var WaitlistEntryDomainObject $updatedEntry */
        $updatedEntry = $this->waitlistEntryRepository->findById($entry->getId());

        SendWaitlistOfferEmailJob::dispatch($updatedEntry, $order->getShortId(), $sessionIdentifier);

        return $updatedEntry;
    }

    private function createReservedOrder(
        WaitlistEntryDomainObject $entry,
        EventDomainObject         $event,
        EventSettingDomainObject  $eventSettings,
        string                    $sessionIdentifier,
    ): OrderDomainObject
    {
        $timeoutMinutes = $eventSettings->getWaitlistOfferTimeoutMinutes() ?? self::DEFAULT_OFFER_TIMEOUT_MINUTES;

        $order = $this->orderManagementService->createNewOrder(
            eventId: $event->getId(),
            event: $event,
            timeOutMinutes: $timeoutMinutes,
            locale: $entry->getLocale(),
            promoCode: null,
            sessionId: $sessionIdentifier,
        );

        $productPrice = $this->productPriceRepository->findById($entry->getProductPriceId());

        $product = $this->productRepository
            ->loadRelation(TaxAndFeesDomainObject::class)
            ->loadRelation(ProductPriceDomainObject::class)
            ->findById($productPrice->getProductId());

        $orderDetails = collect([
            new ProductOrderDetailsDTO(
                product_id: $product->getId(),
                quantities: collect([
                    new OrderProductPriceDTO(
                        quantity: 1,
                        price_id: $productPrice->getId(),
                    ),
                ]),
            ),
        ]);

        $orderItems = $this->orderItemProcessingService->process(
            order: $order,
            productsOrderDetails: $orderDetails,
            event: $event,
            promoCode: null,
        );

        return $this->orderManagementService->updateOrderTotals($order, $orderItems);
    }

    private function getAvailableCountForPrice(object $quantities, int $priceId): int
    {
        foreach ($quantities->productQuantities as $productQuantity) {
            if ($productQuantity->price_id === $priceId) {
                $available = max(0, $productQuantity->quantity_available);
                if ($available === Constants::INFINITE) {
                    return Constants::INFINITE;
                }
                return $available;
            }
        }

        return 0;
    }

    private function calculateOfferExpiry(EventSettingDomainObject $eventSettings): string
    {
        $timeoutMinutes = $eventSettings->getWaitlistOfferTimeoutMinutes() ?? self::DEFAULT_OFFER_TIMEOUT_MINUTES;

        return now()->addMinutes($timeoutMinutes)->toDateTimeString();
    }
}
