<?php

namespace HiEvents\Services\Application\Handlers\Event;

use Closure;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicEventDTO;
use HiEvents\Services\Domain\Event\EventPageViewIncrementService;
use HiEvents\Services\Domain\Product\ProductFilterService;

class GetPublicEventHandler
{
    public const MAX_PUBLIC_OCCURRENCES = 200;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly PromoCodeRepositoryInterface $promoCodeRepository,
        private readonly ProductFilterService $productFilterService,
        private readonly EventPageViewIncrementService $eventPageViewIncrementService,
    ) {}

    public function handle(GetPublicEventDTO $data): EventDomainObject
    {
        $event = $this->eventRepository
            ->loadRelation(
                new Relationship(ProductCategoryDomainObject::class, [
                    new Relationship(ProductDomainObject::class,
                        nested: [
                            new Relationship(ProductPriceDomainObject::class),
                            new Relationship(TaxAndFeesDomainObject::class),
                        ],
                        orderAndDirections: [
                            new OrderAndDirection('order', 'asc'),
                        ]
                    ),
                ])
            )
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->loadRelation(new Relationship(ImageDomainObject::class))
            ->loadRelation(new Relationship(OrganizerDomainObject::class, nested: [
                new Relationship(ImageDomainObject::class),
                new Relationship(OrganizerSettingDomainObject::class),
                new Relationship(domainObject: LocationDomainObject::class, name: 'location_record'),
            ], name: 'organizer'))
            ->findById($data->eventId);

        $hideSoldOutOccurrences = $event->getType() === EventType::RECURRING->name
            && ($event->getEventSettings()?->getHideSoldOutOccurrences() ?? false)
            && ! $this->eventHasWaitlistEnabledProducts($event);

        $occurrenceWhere = [
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $data->eventId,
            [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
        ];

        if ($event->getType() === EventType::RECURRING->name) {
            $occurrenceWhere[] = self::isNotEnded();
        }

        if ($hideSoldOutOccurrences) {
            $occurrenceWhere[] = self::hasRemainingCapacity();
        }

        $occurrences = $this->occurrenceRepository
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->findWhere(
                where: $occurrenceWhere,
                orderAndDirections: [
                    new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE, 'asc'),
                ],
                limit: self::MAX_PUBLIC_OCCURRENCES + 1,
            );

        $verifiedOccurrence = null;
        if ($data->eventOccurrenceId !== null) {
            $verifiedOccurrence = $occurrences->first(
                fn (EventOccurrenceDomainObject $o) => $o->getId() === $data->eventOccurrenceId
            );
            if ($verifiedOccurrence === null) {
                $fallbackWhere = [
                    EventOccurrenceDomainObjectAbstract::ID => $data->eventOccurrenceId,
                    EventOccurrenceDomainObjectAbstract::EVENT_ID => $data->eventId,
                    [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
                ];

                if ($hideSoldOutOccurrences) {
                    $fallbackWhere[] = self::hasRemainingCapacity();
                }

                $verifiedOccurrence = $this->occurrenceRepository
                    ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                        new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                    ]))
                    ->findFirstWhere($fallbackWhere);
            }
            if ($verifiedOccurrence !== null && $verifiedOccurrence->isPast()) {
                $verifiedOccurrence = null;
            }
        }

        $verifiedOccurrenceId = $verifiedOccurrence?->getId();

        if ($occurrences->count() > self::MAX_PUBLIC_OCCURRENCES) {
            $occurrences = $occurrences->take(self::MAX_PUBLIC_OCCURRENCES)->values();
        }

        if ($verifiedOccurrence !== null
            && ! $occurrences->contains(fn (EventOccurrenceDomainObject $o) => $o->getId() === $verifiedOccurrenceId)) {
            $occurrences->push($verifiedOccurrence);
        }

        $event->setEventOccurrences($occurrences);

        if ($hideSoldOutOccurrences && $occurrences->isEmpty()) {
            $event->setUpcomingOccurrencesSoldOut(
                $this->occurrenceRepository->findFirstWhere([
                    EventOccurrenceDomainObjectAbstract::EVENT_ID => $data->eventId,
                    [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
                    self::isNotEnded(),
                    static function ($query): void {
                        $query->whereColumn(
                            EventOccurrenceDomainObjectAbstract::USED_CAPACITY,
                            '>=',
                            EventOccurrenceDomainObjectAbstract::CAPACITY,
                        );
                    },
                ]) !== null
            );
        }

        $promoCodeDomainObject = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::EVENT_ID => $data->eventId,
            PromoCodeDomainObjectAbstract::CODE => $data->promoCode,
        ]);

        if (! $promoCodeDomainObject?->isValid()) {
            $promoCodeDomainObject = null;
        }

        if (! $data->isAuthenticated) {
            $this->eventPageViewIncrementService->increment($data->eventId, $data->ipAddress);
        }

        return $event->setProductCategories($this->productFilterService->filter(
            productsCategories: $event->getProductCategories(),
            promoCode: $promoCodeDomainObject,
            eventOccurrenceId: $verifiedOccurrenceId,
        ));
    }

    private function eventHasWaitlistEnabledProducts(EventDomainObject $event): bool
    {
        return $event->getProductCategories()
            ?->contains(
                fn (ProductCategoryDomainObject $category) => $category->getProducts()
                    ?->contains(fn (ProductDomainObject $product) => $product->getWaitlistEnabled() === true) ?? false
            ) ?? false;
    }

    private static function hasRemainingCapacity(): Closure
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

    private static function isNotEnded(): Closure
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
}
