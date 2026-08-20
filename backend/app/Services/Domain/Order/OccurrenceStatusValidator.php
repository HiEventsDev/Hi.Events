<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;

class OccurrenceStatusValidator
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function assertOrderOccurrencesArePurchasable(OrderDomainObject $order): void
    {
        foreach ($this->resolveOccurrences($order) as $occurrence) {
            if ($occurrence->isCancelled()) {
                throw new ResourceConflictException(__('This event date has been cancelled'));
            }

            if ($occurrence->isPast()) {
                throw new ResourceConflictException(__('This event date has already ended'));
            }
        }
    }

    public function findBlockingOccurrence(OrderDomainObject $order): ?EventOccurrenceDomainObject
    {
        foreach ($this->resolveOccurrences($order) as $occurrence) {
            if ($occurrence->isCancelled() || $occurrence->isPast()) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * @return iterable<EventOccurrenceDomainObject>
     */
    private function resolveOccurrences(OrderDomainObject $order): iterable
    {
        $occurrenceIds = $order->getOrderItems()
            ?->map(fn (OrderItemDomainObject $item) => $item->getEventOccurrenceId())
            ->filter()
            ->unique()
            ->values();

        if ($occurrenceIds === null || $occurrenceIds->isEmpty()) {
            return [];
        }

        return $this->occurrenceRepository->findWhereIn('id', $occurrenceIds->toArray());
    }
}
