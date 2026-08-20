<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use Illuminate\Validation\ValidationException;

class OccurrencePurchaseEligibilityService
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly ProductOccurrenceVisibilityRepositoryInterface $productOccurrenceVisibilityRepository,
    ) {}

    /**
     * @throws ValidationException
     */
    public function assertOccurrencePurchasable(
        int $eventId,
        int $occurrenceId,
        int $additionalQuantity = 1,
        bool $overrideCapacity = false,
        ?EventOccurrenceDomainObject $occurrence = null,
        ?int $reservedQuantity = null,
    ): EventOccurrenceDomainObject {
        $occurrence ??= $this->occurrenceRepository->findFirstWhere([
            'id' => $occurrenceId,
            'event_id' => $eventId,
        ]);

        if ($occurrence === null || $occurrence->getEventId() !== $eventId) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => __('The specified event occurrence was not found'),
            ]);
        }

        if ($occurrence->isCancelled()) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => $this->purchasabilityMessage(
                    $eventId,
                    __('This event occurrence has been cancelled'),
                    __('This event has been cancelled'),
                ),
            ]);
        }

        if ($occurrence->isPast()) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => $this->purchasabilityMessage(
                    $eventId,
                    __('This event occurrence has already ended'),
                    __('This event has already ended'),
                ),
            ]);
        }

        if ($overrideCapacity || $additionalQuantity <= 0) {
            return $occurrence;
        }

        if ($occurrence->isSoldOut()) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => $this->purchasabilityMessage(
                    $eventId,
                    __('This event occurrence is sold out'),
                    __('This event is sold out'),
                ),
            ]);
        }

        if ($occurrence->getCapacity() !== null) {
            $reservedForOccurrence = $reservedQuantity
                ?? $this->orderItemRepository->getReservedQuantityForOccurrence($occurrenceId);

            $available = $occurrence->getCapacity() - $occurrence->getUsedCapacity() - $reservedForOccurrence;
            if ($additionalQuantity > $available) {
                throw ValidationException::withMessages([
                    'event_occurrence_id' => $this->purchasabilityMessage(
                        $eventId,
                        __('Not enough capacity available for this occurrence'),
                        __('Not enough capacity available for this event'),
                    ),
                ]);
            }
        }

        return $occurrence;
    }

    /**
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

    private function purchasabilityMessage(int $eventId, string $multiOccurrence, string $singleOccurrence): string
    {
        return $this->eventHasMultipleOccurrences($eventId) ? $multiOccurrence : $singleOccurrence;
    }

    private function eventHasMultipleOccurrences(int $eventId): bool
    {
        return $this->occurrenceRepository->countWhere(['event_id' => $eventId]) > 1;
    }
}
