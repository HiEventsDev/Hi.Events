<?php

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\EventLifecycleStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicOrganizerEventsDTO;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Product\DTO\ProductFilterEventContextDTO;
use HiEvents\Services\Domain\Product\ProductFilterService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GetPublicEventsHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly ProductFilterService $productFilterService,
        private readonly AvailableProductQuantitiesFetchService $productQuantitiesFetchService,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
    ) {}

    public function handle(GetPublicOrganizerEventsDTO $dto): LengthAwarePaginator
    {
        $organizer = $this->organizerRepository
            ->loadRelation(new Relationship(
                domainObject: OrganizerConfigurationDomainObject::class,
                name: 'organizer_configuration',
            ))
            ->findById($dto->organizerId);
        $isOrganizersOwnView = $dto->authenticatedAccountId
            && $organizer->getAccountId() === $dto->authenticatedAccountId;

        return $this->fetchEvents($dto, (bool) $isOrganizersOwnView, $organizer->getOrganizerConfiguration());
    }

    private function fetchEvents(
        GetPublicOrganizerEventsDTO $dto,
        bool $isOrganizersOwnView,
        ?OrganizerConfigurationDomainObject $organizerConfiguration,
    ): LengthAwarePaginator {
        $query = $this->eventRepository
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ], name: 'event_location'))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, nested: [
                new Relationship(domainObject: EventLocationDomainObject::class, nested: [
                    new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                ], name: 'event_location'),
            ]))
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
            ->loadRelation(new Relationship(ImageDomainObject::class));

        $events = $isOrganizersOwnView
            ? $query->findEventsForOrganizer(
                organizerId: $dto->organizerId,
                accountId: $dto->authenticatedAccountId,
                params: $dto->queryParams
            )
            : $query->findEvents(
                where: [
                    'organizer_id' => $dto->organizerId,
                    'status' => EventStatus::LIVE->name,
                ],
                params: $dto->queryParams
            );

        [$endedEvents, $onSaleEvents] = $events->getCollection()
            ->filter(fn (EventDomainObject $event) => $event->getProductCategories() !== null)
            ->partition(fn (EventDomainObject $event) => $event->getLifecycleStatus() === EventLifecycleStatus::ENDED->name);

        $endedEvents->each(fn (EventDomainObject $event) => $event->setProductCategories(collect()));

        $this->eventSeatMapLookup->findSummariesForEvents(
            $onSaleEvents->map(fn (EventDomainObject $event) => $event->getId())->values()->all(),
        );
        $productQuantities = $this->productQuantitiesFetchService->getEventWideQuantitiesForEvents($onSaleEvents);

        $onSaleEvents->each(fn (EventDomainObject $event) => $this->applyPublicProductVisibility(
            event: $event,
            organizerConfiguration: $organizerConfiguration,
            productQuantities: $productQuantities[$event->getId()],
        ));

        return $events;
    }

    private function applyPublicProductVisibility(
        EventDomainObject $event,
        ?OrganizerConfigurationDomainObject $organizerConfiguration,
        AvailableProductQuantitiesResponseDTO $productQuantities,
    ): void {
        $categories = $event->getProductCategories();
        $products = $categories
            ->reject(fn (ProductCategoryDomainObject $category) => $category->getIsHidden())
            ->flatMap(fn (ProductCategoryDomainObject $category) => $category->getProducts() ?? collect());

        $event->setProductCategories($this->productFilterService->filter(
            productsCategories: $categories,
            eventContext: new ProductFilterEventContextDTO(
                event: $event,
                organizerConfiguration: $organizerConfiguration,
                productQuantities: $productQuantities,
            ),
        ));
        $event->setProductsSoldOut($this->arePublicProductsSoldOut($products));
    }

    private function arePublicProductsSoldOut(Collection $products): bool
    {
        $publicProducts = $products->reject(fn (ProductDomainObject $product) => $product->getIsHidden()
            || $product->getIsHiddenWithoutPromoCode()
            || $product->getIsAddonOnly());

        return $publicProducts->isNotEmpty()
            && $publicProducts->every(fn (ProductDomainObject $product) => $product->isSoldOut());
    }
}
