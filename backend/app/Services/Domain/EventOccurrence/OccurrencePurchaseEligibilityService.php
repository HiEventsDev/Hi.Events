<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for "can this occurrence currently receive a purchase?".
 *
 * Used by both the public checkout validator and manual-attendee creation so the
 * two paths cannot drift apart. Intentionally narrow:
 *
 *  - status / visibility / capacity checks live here
 *  - per-product per-price availability and quantity math live in the existing
 *    `AvailableProductQuantitiesFetchService`; callers compose the two
 *
 * Consolidating here matters because the manual-attendee path previously skipped
 * all of these checks, letting organisers issue tickets against cancelled or
 * sold-out occurrences and bypass occurrence-scoped product visibility rules.
 */
class OccurrencePurchaseEligibilityService
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly ProductOccurrenceVisibilityRepositoryInterface $productOccurrenceVisibilityRepository,
    ) {}

    /**
     * Loads and validates the occurrence is eligible to receive an additional
     * purchase of the given quantity. Returns the loaded domain object on
     * success — saves the caller a duplicate fetch.
     *
     * `$overrideCapacity` is for organiser-initiated manual creation only; it
     * skips the capacity ceiling but still enforces status (a cancelled
     * occurrence has no seats to override into).
     *
     * @throws ValidationException
     */
    public function assertOccurrencePurchasable(
        int $eventId,
        int $occurrenceId,
        int $additionalQuantity = 1,
        bool $overrideCapacity = false,
    ): EventOccurrenceDomainObject {
        $occurrence = $this->occurrenceRepository->findFirstWhere([
            'id' => $occurrenceId,
            'event_id' => $eventId,
        ]);

        if ($occurrence === null) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => __('The specified event occurrence was not found'),
            ]);
        }

        if ($occurrence->isCancelled()) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => __('This event occurrence has been cancelled'),
            ]);
        }

        // Past dates are blocked even when capacity is overridden — selling or
        // manually issuing tickets for a session that has already ended is
        // never the intended behaviour, and the public payload already filters
        // these out so any request reaching here is stale or hand-crafted.
        if ($occurrence->isPast()) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => __('This event occurrence has already ended'),
            ]);
        }

        // SOLD_OUT is a capacity-derived status (ProductQuantityUpdateService
        // flips it whenever used_capacity >= capacity), so blocking it here
        // before the capacity-override branch would defeat the override flag in
        // the most common case it was added for. Treat SOLD_OUT as a normal
        // capacity gate that the override can bypass.
        if (! $overrideCapacity && $occurrence->isSoldOut()) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => __('This event occurrence is sold out'),
            ]);
        }

        if (! $overrideCapacity && $occurrence->getCapacity() !== null) {
            $reservedForOccurrence = $this->orderItemRepository
                ->getReservedQuantityForOccurrence($occurrenceId);

            $available = $occurrence->getCapacity() - $occurrence->getUsedCapacity() - $reservedForOccurrence;
            if ($additionalQuantity > $available) {
                throw ValidationException::withMessages([
                    'event_occurrence_id' => __('Not enough capacity available for this occurrence'),
                ]);
            }
        }

        return $occurrence;
    }

    /**
     * Verifies the product is visible on the occurrence. Visibility rules are an
     * allow-list — an occurrence with no rules is treated as "all products
     * visible" (the default), matching `ProductFilterService::filterByOccurrenceVisibility`
     * so the validator and the storefront filter agree.
     *
     * @param  int[]  $productIds
     *
     * @throws ValidationException
     */
    public function assertProductsVisibleOnOccurrence(int $occurrenceId, array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $rules = $this->productOccurrenceVisibilityRepository
            ->findWhereIn('event_occurrence_id', [$occurrenceId]);

        if ($rules->isEmpty()) {
            return;
        }

        $visibleProductIds = $rules
            ->map(fn ($rule) => $rule->getProductId())
            ->all();

        foreach ($productIds as $productId) {
            if (! in_array($productId, $visibleProductIds, true)) {
                throw ValidationException::withMessages([
                    'event_occurrence_id' => __('One or more selected products are not available for this occurrence'),
                ]);
            }
        }
    }
}
