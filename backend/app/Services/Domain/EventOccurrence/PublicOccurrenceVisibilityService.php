<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventOccurrence;

use Closure;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;

class PublicOccurrenceVisibilityService
{
    public function shouldHideSoldOutOccurrences(EventDomainObject $event): bool
    {
        return $event->getType() === EventType::RECURRING->name
            && ($event->getEventSettings()?->getHideSoldOutOccurrences() ?? false)
            && ! $this->eventHasWaitlistEnabledProducts($event);
    }

    public function buildWhereConditions(int $eventId, bool $isRecurring, bool $hideSoldOutOccurrences): array
    {
        $where = [
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
        ];

        if ($isRecurring) {
            $where[] = self::isNotEnded();
        }

        if ($hideSoldOutOccurrences) {
            $where[] = self::hasRemainingCapacity();
        }

        return $where;
    }

    public static function hasRemainingCapacity(): Closure
    {
        return static function ($query): void {
            $query->whereNull(EventOccurrenceDomainObjectAbstract::CAPACITY)
                ->orWhereColumn(
                    EventOccurrenceDomainObjectAbstract::USED_CAPACITY,
                    '<',
                    EventOccurrenceDomainObjectAbstract::CAPACITY,
                );
        };
    }

    public static function isNotEnded(): Closure
    {
        return static function ($query): void {
            $query->whereRaw(
                sprintf(
                    'COALESCE(%s, %s) >= ?',
                    EventOccurrenceDomainObjectAbstract::END_DATE,
                    EventOccurrenceDomainObjectAbstract::START_DATE,
                ),
                [now()->toDateTimeString()],
            );
        };
    }

    private function eventHasWaitlistEnabledProducts(EventDomainObject $event): bool
    {
        return $event->getProductCategories()
            ?->contains(
                fn (ProductCategoryDomainObject $category) => $category->getProducts()
                    ?->contains(fn (ProductDomainObject $product) => $product->getWaitlistEnabled() === true) ?? false
            ) ?? false;
    }
}
