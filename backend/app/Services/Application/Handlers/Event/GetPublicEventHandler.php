<?php

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
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
        private readonly EventRepositoryInterface           $eventRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly PromoCodeRepositoryInterface       $promoCodeRepository,
        private readonly ProductFilterService               $productFilterService,
        private readonly EventPageViewIncrementService      $eventPageViewIncrementService,
    )
    {
    }

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
            ->loadRelation(new Relationship(ImageDomainObject::class))
            ->loadRelation(new Relationship(OrganizerDomainObject::class, nested: [
                new Relationship(ImageDomainObject::class),
                new Relationship(OrganizerSettingDomainObject::class),
            ], name: 'organizer'))
            ->findById($data->eventId);

        $occurrences = $this->occurrenceRepository->findWhere(
            where: [
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $data->eventId,
                [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
                [EventOccurrenceDomainObjectAbstract::START_DATE, '>=', now()->toDateTimeString()],
            ],
            orderAndDirections: [
                new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE, 'asc'),
            ],
        );

        if ($occurrences->count() > self::MAX_PUBLIC_OCCURRENCES) {
            $capped = $occurrences->take(self::MAX_PUBLIC_OCCURRENCES);

            if ($data->eventOccurrenceId) {
                $linked = $occurrences->first(
                    fn(EventOccurrenceDomainObject $o) => $o->getId() === $data->eventOccurrenceId
                );
                if ($linked && !$capped->contains(fn($o) => $o->getId() === $linked->getId())) {
                    $capped->push($linked);
                }
            }

            $occurrences = $capped->values();
        } elseif ($data->eventOccurrenceId) {
            $hasLinked = $occurrences->contains(
                fn(EventOccurrenceDomainObject $o) => $o->getId() === $data->eventOccurrenceId
            );
            if (!$hasLinked) {
                $linked = $this->occurrenceRepository->findFirstWhere([
                    EventOccurrenceDomainObjectAbstract::ID => $data->eventOccurrenceId,
                    EventOccurrenceDomainObjectAbstract::EVENT_ID => $data->eventId,
                ]);
                if ($linked) {
                    $occurrences->push($linked);
                }
            }
        }

        $event->setEventOccurrences($occurrences);

        $promoCodeDomainObject = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::EVENT_ID => $data->eventId,
            PromoCodeDomainObjectAbstract::CODE => $data->promoCode,
        ]);

        if (!$promoCodeDomainObject?->isValid()) {
            $promoCodeDomainObject = null;
        }

        if (!$data->isAuthenticated) {
            $this->eventPageViewIncrementService->increment($data->eventId, $data->ipAddress);
        }

        return $event->setProductCategories($this->productFilterService->filter(
            productsCategories: $event->getProductCategories(),
            promoCode: $promoCodeDomainObject,
            eventOccurrenceId: $data->eventOccurrenceId,
        ));
    }
}
