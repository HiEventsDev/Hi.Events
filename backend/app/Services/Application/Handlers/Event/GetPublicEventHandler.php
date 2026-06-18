<?php

namespace HiEvents\Services\Application\Handlers\Event;

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
use HiEvents\Services\Domain\Branding\BrandingVisibilityService;
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
        private readonly BrandingVisibilityService $brandingVisibilityService,
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
            ->loadRelation(new Relationship(ProductDomainObject::class))
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

        $occurrenceWhere = [
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $data->eventId,
            [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
        ];

        if ($event->getType() === EventType::RECURRING->name) {
            $occurrenceWhere[] = [EventOccurrenceDomainObjectAbstract::START_DATE, '>=', now()->toDateTimeString()];
        }

        // +1 lets us detect overflow without loading the entire occurrence table for
        // long-running recurring events (e.g. daily over multiple years).
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

        // Resolve once: only honour the requested occurrence id if it actually
        // belongs to this event. The caller can supply any id, and downstream
        // ProductFilterService applies visibility/capacity rules for whichever
        // id we pass — so a cross-event id would otherwise leak another event's
        // visibility-altered product payload through this event's response.
        $verifiedOccurrence = null;
        if ($data->eventOccurrenceId !== null) {
            $verifiedOccurrence = $occurrences->first(
                fn (EventOccurrenceDomainObject $o) => $o->getId() === $data->eventOccurrenceId
            );
            if ($verifiedOccurrence === null) {
                $verifiedOccurrence = $this->occurrenceRepository
                    ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                        new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                    ]))
                    ->findFirstWhere([
                        EventOccurrenceDomainObjectAbstract::ID => $data->eventOccurrenceId,
                        EventOccurrenceDomainObjectAbstract::EVENT_ID => $data->eventId,
                        [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
                    ]);
            }
            // The fallback above only filters out CANCELLED — drop past dates
            // here too. Without this, a stale share/email link to a past date
            // resolves successfully, drives `productFilterService->filter` for
            // that occurrence, and then the storefront date picker (which hides
            // past dates) leaves the user with occurrence-filtered products and
            // no selectable date. Treat past-link as "no occurrence verified"
            // and let the payload fall back to the event-wide product set.
            if ($verifiedOccurrence !== null && $verifiedOccurrence->isPast()) {
                $verifiedOccurrence = null;
            }
        }

        $verifiedOccurrenceId = $verifiedOccurrence?->getId();

        if ($occurrences->count() > self::MAX_PUBLIC_OCCURRENCES) {
            $occurrences = $occurrences->take(self::MAX_PUBLIC_OCCURRENCES)->values();
        }

        // Append the verified occurrence when it isn't already in the public
        // payload — covers two cases: (1) the linked occurrence was beyond the
        // capped range for a long-running schedule, and (2) the requested id
        // matched but only via the fallback ownership query (safety net).
        if ($verifiedOccurrence !== null
            && ! $occurrences->contains(fn (EventOccurrenceDomainObject $o) => $o->getId() === $verifiedOccurrenceId)) {
            $occurrences->push($verifiedOccurrence);
        }

        $event->setEventOccurrences($occurrences);

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

        $event->setBrandingRemoved($this->brandingVisibilityService->resolveBrandingRemoved($event));

        return $event->setProductCategories($this->productFilterService->filter(
            productsCategories: $event->getProductCategories(),
            promoCode: $promoCodeDomainObject,
            eventOccurrenceId: $verifiedOccurrenceId,
        ));
    }
}
