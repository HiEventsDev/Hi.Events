<?php

namespace HiEvents\Services\Application\Handlers\Event;

use Carbon\Carbon;
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
use HiEvents\DomainObjects\Status\EventLifecycleStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicEventDTO;
use HiEvents\Services\Application\Handlers\Event\DTO\PublicOccurrenceFetchResultDTO;
use HiEvents\Services\Domain\Event\EventPageViewIncrementService;
use HiEvents\Services\Domain\EventOccurrence\PublicOccurrenceVisibilityService;
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
        private readonly PublicOccurrenceVisibilityService $occurrenceVisibilityService,
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
                            new Relationship(domainObject: ProductDomainObject::class, name: 'addons'),
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

        $isRecurring = $event->getType() === EventType::RECURRING->name;
        $hideSoldOutOccurrences = $this->occurrenceVisibilityService->shouldHideSoldOutOccurrences($event);
        $occurrenceWhere = $this->occurrenceVisibilityService->buildWhereConditions(
            eventId: $data->eventId,
            isRecurring: $isRecurring,
            hideSoldOutOccurrences: $hideSoldOutOccurrences,
        );

        $verifiedOccurrence = $this->resolveVerifiedOccurrence($data, $hideSoldOutOccurrences);

        if ($isRecurring) {
            $this->setRecurringEventOccurrences($event, $data->eventId, $occurrenceWhere, $hideSoldOutOccurrences, $verifiedOccurrence);
        } else {
            $event->setEventOccurrences(
                $this->fetchOccurrences($occurrenceWhere, $verifiedOccurrence)->occurrences
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
            eventOccurrenceId: $verifiedOccurrence?->getId(),
        ));
    }

    private function setRecurringEventOccurrences(
        EventDomainObject $event,
        int $eventId,
        array $occurrenceWhere,
        bool $hideSoldOutOccurrences,
        ?EventOccurrenceDomainObject $verifiedOccurrence,
    ): void {
        $nextBookableWhere = $hideSoldOutOccurrences
            ? $occurrenceWhere
            : [...$occurrenceWhere, PublicOccurrenceVisibilityService::hasRemainingCapacity()];

        $nextBookable = $this->findEdgeOccurrence($nextBookableWhere, 'asc');
        $lastUpcoming = $this->findEdgeOccurrence($occurrenceWhere, 'desc');
        $event->setNextOccurrenceStartDate($nextBookable?->getStartDate());
        $event->setLastOccurrenceStartDate($lastUpcoming?->getStartDate());

        if ($lastUpcoming === null) {
            $this->setLifecycleStatusForEndedEvent($event, $eventId);
        }

        $anchorOccurrence = $verifiedOccurrence ?? $nextBookable;
        if ($anchorOccurrence === null && ! $hideSoldOutOccurrences) {
            $anchorOccurrence = $this->findEdgeOccurrence($occurrenceWhere, 'asc');
        }

        $timezone = $event->getTimezone() ?: 'UTC';
        $anchorMonthStart = Carbon::parse($anchorOccurrence?->getStartDate() ?? now(), 'UTC')
            ->setTimezone($timezone)
            ->startOfMonth();

        $monthWhere = [
            ...$occurrenceWhere,
            [EventOccurrenceDomainObjectAbstract::START_DATE, '>=', $anchorMonthStart->copy()->utc()->toDateTimeString()],
            [EventOccurrenceDomainObjectAbstract::START_DATE, '<=', $anchorMonthStart->copy()->endOfMonth()->utc()->toDateTimeString()],
        ];

        $result = $this->fetchOccurrences($monthWhere, $verifiedOccurrence);

        $event->setEventOccurrences($result->occurrences);
        $event->setOccurrencesMonth($result->truncated ? null : $anchorMonthStart->format('Y-m'));

        if ($hideSoldOutOccurrences && $nextBookable === null) {
            $event->setUpcomingOccurrencesSoldOut(
                $this->occurrenceRepository->findFirstWhere([
                    EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
                    [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
                    PublicOccurrenceVisibilityService::isNotEnded(),
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
    }

    private function setLifecycleStatusForEndedEvent(EventDomainObject $event, int $eventId): void
    {
        $latestOccurrence = $this->findEdgeOccurrence([
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
        ], 'desc');

        if ($latestOccurrence?->isPast()) {
            $event->setLifecycleStatus(EventLifecycleStatus::ENDED->name);
        }
    }

    private function fetchOccurrences(array $where, ?EventOccurrenceDomainObject $verifiedOccurrence): PublicOccurrenceFetchResultDTO
    {
        $occurrences = $this->occurrenceRepository
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->findWhere(
                where: $where,
                orderAndDirections: [
                    new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE, 'asc'),
                ],
                limit: self::MAX_PUBLIC_OCCURRENCES + 1,
            );

        $truncated = $occurrences->count() > self::MAX_PUBLIC_OCCURRENCES;
        if ($truncated) {
            $occurrences = $occurrences->take(self::MAX_PUBLIC_OCCURRENCES)->values();
        }

        if ($verifiedOccurrence !== null
            && ! $occurrences->contains(fn (EventOccurrenceDomainObject $o) => $o->getId() === $verifiedOccurrence->getId())) {
            $occurrences->push($verifiedOccurrence);
        }

        return new PublicOccurrenceFetchResultDTO($occurrences, $truncated);
    }

    private function findEdgeOccurrence(array $where, string $direction): ?EventOccurrenceDomainObject
    {
        return $this->occurrenceRepository
            ->findWhere(
                where: $where,
                orderAndDirections: [
                    new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE, $direction),
                ],
                limit: 1,
            )
            ->first();
    }

    private function resolveVerifiedOccurrence(GetPublicEventDTO $data, bool $hideSoldOutOccurrences): ?EventOccurrenceDomainObject
    {
        if ($data->eventOccurrenceId === null) {
            return null;
        }

        $where = [
            EventOccurrenceDomainObjectAbstract::ID => $data->eventOccurrenceId,
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $data->eventId,
            [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
        ];

        if ($hideSoldOutOccurrences) {
            $where[] = PublicOccurrenceVisibilityService::hasRemainingCapacity();
        }

        $verifiedOccurrence = $this->occurrenceRepository
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->findFirstWhere($where);

        if ($verifiedOccurrence !== null && $verifiedOccurrence->isPast()) {
            return null;
        }

        return $verifiedOccurrence;
    }
}
