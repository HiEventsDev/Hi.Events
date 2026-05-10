<?php

namespace HiEvents\Services\Domain\Waitlist;

use HiEvents\Constants;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Exceptions\NoCapacityAvailableException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Waitlist\SendWaitlistOfferEmailJob;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcessWaitlistService
{
    private const DEFAULT_OFFER_TIMEOUT_MINUTES = 60 * 12; // 12 hours

    public function __construct(
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly OrderManagementService $orderManagementService,
        private readonly OrderItemProcessingService $orderItemProcessingService,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AvailableProductQuantitiesFetchService $availableQuantitiesService,
        private readonly ProductPriceRepositoryInterface $productPriceRepository,
        private readonly EventOccurrenceRepositoryInterface $eventOccurrenceRepository,
        private readonly OccurrencePurchaseEligibilityService $eligibilityService,
    ) {}

    /**
     * @return Collection<int, WaitlistEntryDomainObject>
     */
    public function offerToNext(
        int $productPriceId,
        int $quantity,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        ?int $eventOccurrenceId = null,
    ): Collection {
        if ($quantity <= 0) {
            throw new NoCapacityAvailableException(
                __('No capacity available for the selected waitlist entries.')
            );
        }

        return $this->databaseManager->transaction(function () use ($productPriceId, $quantity, $event, $eventSettings, $eventOccurrenceId) {
            $this->databaseManager->statement('SELECT pg_advisory_xact_lock(?)', [$event->getId()]);
            $this->waitlistEntryRepository->lockForProductPrice($productPriceId, $eventOccurrenceId);

            $entries = $eventOccurrenceId !== null
                ? $this->waitlistEntryRepository->getNextWaitingEntries($productPriceId, eventOccurrenceId: $eventOccurrenceId)
                : $this->waitlistEntryRepository->getNextWaitingEntries($productPriceId);

            if ($entries->isEmpty()) {
                throw new NoCapacityAvailableException(
                    __('There are no waiting entries for this product')
                );
            }

            $offeredEntries = collect();
            // Capacity is fetched once per (occurrence, price) and decremented
            // in-flight as offers are issued, so the loop neither re-runs the
            // multi-CTE availability query per entry nor over-offers when
            // multiple waiting entries share an occurrence.
            $remainingByOccurrenceAndPrice = [];

            foreach ($entries as $entry) {
                try {
                    $occurrenceId = $this->resolveEventOccurrenceId($event, $entry);
                } catch (ResourceConflictException|ResourceNotFoundException) {
                    continue;
                }

                // Re-validate the occurrence + product visibility right before
                // offering. The entry was validated when it was created, but
                // the date can be cancelled, age past, or have its product
                // visibility changed before this listener fires. Skip those
                // entries silently — they should be cancelled out-of-band by
                // the cancel/regen flows, but defending here means a stale
                // entry never produces an offer email pointing at a dead date.
                if (! $this->isOccurrenceStillEligibleForEntry($event, $occurrenceId, $entry)) {
                    continue;
                }

                $priceId = $entry->getProductPriceId();

                if (! isset($remainingByOccurrenceAndPrice[$occurrenceId][$priceId])) {
                    $quantities = $this->availableQuantitiesService->getAvailableProductQuantities(
                        $event->getId(),
                        ignoreCache: true,
                        eventOccurrenceId: $occurrenceId,
                    );
                    $remainingByOccurrenceAndPrice[$occurrenceId][$priceId] = $this->getAvailableCountForPrice($quantities, $priceId);
                }

                if ($remainingByOccurrenceAndPrice[$occurrenceId][$priceId] <= 0) {
                    continue;
                }

                $updatedEntry = $this->offerEntry($entry, $event, $eventSettings);
                $offeredEntries->push($updatedEntry);

                if ($remainingByOccurrenceAndPrice[$occurrenceId][$priceId] !== Constants::INFINITE) {
                    $remainingByOccurrenceAndPrice[$occurrenceId][$priceId]--;
                }

                if ($offeredEntries->count() >= $quantity) {
                    break;
                }
            }

            if ($offeredEntries->isEmpty()) {
                throw new NoCapacityAvailableException(
                    __('No capacity available for the selected waitlist entries.')
                );
            }

            return $offeredEntries;
        });
    }

    /**
     * @return Collection<int, WaitlistEntryDomainObject>
     */
    public function offerSpecificEntry(
        int $entryId,
        int $eventId,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
    ): Collection {
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
            if (! in_array($entry->getStatus(), $validStatuses, true)) {
                throw new ResourceConflictException(
                    __('This waitlist entry cannot be offered in its current status')
                );
            }

            $this->waitlistEntryRepository->lockForProductPrice(
                $entry->getProductPriceId(),
                $entry->getEventOccurrenceId(),
            );

            try {
                $occurrenceId = $this->resolveEventOccurrenceId($event, $entry);
            } catch (ResourceConflictException|ResourceNotFoundException $e) {
                throw new ResourceConflictException(
                    __('This waitlist entry is no longer linked to a valid event date.'),
                    previous: $e,
                );
            }

            if (! $this->isOccurrenceStillEligibleForEntry($event, $occurrenceId, $entry)) {
                throw new ResourceConflictException(
                    __('This event date is no longer available for this product.')
                );
            }

            if (! $this->hasCapacityForEntry($entry, $event)) {
                throw new NoCapacityAvailableException(
                    __('No capacity available to offer this waitlist entry. You will need to increase the available quantity for the product or date.')
                );
            }

            $updatedEntry = $this->offerEntry($entry, $event, $eventSettings);

            return collect([$updatedEntry]);
        });
    }

    /**
     * Checks the occurrence is still cancellable / not past, and that the
     * waitlisted product remains visible on it. Wraps the eligibility service
     * with `overrideCapacity: true` because the caller has already done its
     * own capacity arithmetic and we only want the lifecycle gates here.
     */
    private function isOccurrenceStillEligibleForEntry(
        EventDomainObject $event,
        int $occurrenceId,
        WaitlistEntryDomainObject $entry,
    ): bool {
        try {
            $this->eligibilityService->assertOccurrencePurchasable(
                eventId: $event->getId(),
                occurrenceId: $occurrenceId,
                additionalQuantity: 1,
                overrideCapacity: true,
            );

            $productPrice = $this->productPriceRepository->findById($entry->getProductPriceId());
            if ($productPrice === null) {
                return false;
            }

            $this->eligibilityService->assertProductsVisibleOnOccurrence(
                $occurrenceId,
                [$productPrice->getProductId()],
            );
        } catch (ValidationException) {
            return false;
        }

        return true;
    }

    private function offerEntry(
        WaitlistEntryDomainObject $entry,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
    ): WaitlistEntryDomainObject {
        $offerExpiresAt = $this->calculateOfferExpiry($eventSettings);
        $sessionIdentifier = sha1(Str::uuid().Str::random(40));
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
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        string $sessionIdentifier,
    ): OrderDomainObject {
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

        $eventOccurrenceId = $this->resolveEventOccurrenceId($event, $entry);

        $orderDetails = collect([
            new ProductOrderDetailsDTO(
                product_id: $product->getId(),
                quantities: collect([
                    new OrderProductPriceDTO(
                        quantity: 1,
                        price_id: $productPrice->getId(),
                    ),
                ]),
                event_occurrence_id: $eventOccurrenceId,
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

    private function resolveEventOccurrenceId(EventDomainObject $event, WaitlistEntryDomainObject $entry): ?int
    {
        if ($entry->getEventOccurrenceId() !== null) {
            return $entry->getEventOccurrenceId();
        }

        if ($event->isRecurring()) {
            throw new ResourceConflictException(__('Waitlist entry is missing an event date.'));
        }

        $occurrence = $this->eventOccurrenceRepository
            ->findWhere(
                where: [
                    EventOccurrenceDomainObjectAbstract::EVENT_ID => $event->getId(),
                ],
                orderAndDirections: [
                    new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE, 'asc'),
                ],
            )
            ->first();

        if ($occurrence === null) {
            throw new ResourceNotFoundException(__('No occurrence found for this event.'));
        }

        return $occurrence->getId();
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

    private function hasCapacityForEntry(WaitlistEntryDomainObject $entry, EventDomainObject $event): bool
    {
        // resolveEventOccurrenceId can throw two ways for an entry we cannot
        // confidently route:
        //   - ResourceConflictException: orphan entry on a recurring event
        //     (FK cascaded null after delete)
        //   - ResourceNotFoundException: single event has no occurrences at
        //     all (degenerate state, but possible during creation flows)
        // Either case → treat as "no capacity" so the offer batch skips it
        // instead of crashing the entire waitlist run for that price.
        try {
            $eventOccurrenceId = $this->resolveEventOccurrenceId($event, $entry);
        } catch (ResourceConflictException|ResourceNotFoundException) {
            return false;
        }

        $quantities = $this->availableQuantitiesService->getAvailableProductQuantities(
            $event->getId(),
            ignoreCache: true,
            eventOccurrenceId: $eventOccurrenceId,
        );

        return $this->getAvailableCountForPrice($quantities, $entry->getProductPriceId()) > 0;
    }

    private function calculateOfferExpiry(EventSettingDomainObject $eventSettings): string
    {
        $timeoutMinutes = $eventSettings->getWaitlistOfferTimeoutMinutes() ?? self::DEFAULT_OFFER_TIMEOUT_MINUTES;

        return now()->addMinutes($timeoutMinutes)->toDateTimeString();
    }
}
