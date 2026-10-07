<?php

namespace Tests\Unit\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\EventLifecycleStatus;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicOrganizerEventsDTO;
use HiEvents\Services\Application\Handlers\Event\GetPublicEventsHandler;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Product\DTO\ProductFilterEventContextDTO;
use HiEvents\Services\Domain\Product\ProductFilterService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class GetPublicEventsHandlerTest extends TestCase
{
    private EventRepositoryInterface $eventRepository;

    private OrganizerRepositoryInterface $organizerRepository;

    private ProductFilterService $productFilterService;

    private AvailableProductQuantitiesFetchService $productQuantitiesFetchService;

    private EventSeatMapLookupService $eventSeatMapLookup;

    /** @var array<int, int[]> */
    private array $quantityFetches = [];

    /** @var array<int, AvailableProductQuantitiesResponseDTO> */
    private array $quantitiesByEventId = [];

    private GetPublicEventsHandler $handler;

    private OrganizerConfigurationDomainObject $organizerConfiguration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->productFilterService = m::mock(ProductFilterService::class);
        $this->productQuantitiesFetchService = m::mock(AvailableProductQuantitiesFetchService::class);
        $this->eventSeatMapLookup = m::mock(EventSeatMapLookupService::class);
        $this->eventSeatMapLookup->shouldReceive('findSummariesForEvents')->byDefault()->andReturn([]);
        $this->productQuantitiesFetchService->shouldReceive('getEventWideQuantitiesForEvents')
            ->andReturnUsing(function (Collection $events) {
                $this->quantityFetches[] = $events->map(fn (EventDomainObject $event) => $event->getId())->values()->all();

                return $events
                    ->mapWithKeys(fn (EventDomainObject $event) => [
                        $event->getId() => $this->quantitiesByEventId[$event->getId()] ??= new AvailableProductQuantitiesResponseDTO(collect()),
                    ])
                    ->all();
            });

        $this->organizerConfiguration = new OrganizerConfigurationDomainObject;

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->organizerRepository->shouldReceive('findById')
            ->andReturn((new OrganizerDomainObject)
                ->setAccountId(10)
                ->setOrganizerConfiguration($this->organizerConfiguration));

        $this->handler = new GetPublicEventsHandler(
            $this->eventRepository,
            $this->organizerRepository,
            $this->productFilterService,
            $this->productQuantitiesFetchService,
            $this->eventSeatMapLookup,
        );
    }

    public function test_upcoming_event_products_pass_through_the_public_product_filter(): void
    {
        $unfiltered = collect([new ProductCategoryDomainObject]);
        $filtered = collect([(new ProductCategoryDomainObject)->setName('Visible only')]);
        $event = (new EventDomainObject)
            ->setId(1)
            ->setLifecycleStatus(EventLifecycleStatus::UPCOMING->name)
            ->setProductCategories($unfiltered);

        $this->eventRepository->shouldReceive('findEvents')->once()->andReturn($this->paginate([$event]));
        $this->productFilterService->shouldReceive('filter')
            ->once()
            ->withArgs(fn (Collection $categories) => $categories === $unfiltered)
            ->andReturn($filtered);

        $result = $this->handler->handle($this->dto());

        $this->assertSame($filtered, $result->items()[0]->getProductCategories());
    }

    public function test_the_filter_reuses_the_listed_event_and_organizer_configuration(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setLifecycleStatus(EventLifecycleStatus::UPCOMING->name)
            ->setProductCategories(collect([new ProductCategoryDomainObject]));

        $this->eventRepository->shouldReceive('findEvents')->once()->andReturn($this->paginate([$event]));
        $this->productFilterService->shouldReceive('filter')
            ->once()
            ->withArgs(function (...$args) use ($event) {
                $context = collect($args)->first(fn ($arg) => $arg instanceof ProductFilterEventContextDTO);

                return $context?->event === $event
                    && $context->organizerConfiguration === $this->organizerConfiguration
                    && $context->productQuantities === $this->quantitiesByEventId[1];
            })
            ->andReturn(collect());

        $this->handler->handle($this->dto());
    }

    public function test_an_event_whose_public_products_are_all_sold_out_is_flagged(): void
    {
        $event = $this->eventWithProducts([
            $this->product(quantityAvailable: 0, hideWhenSoldOut: true),
            $this->product(quantityAvailable: 5, isHidden: true),
        ]);

        $this->eventRepository->shouldReceive('findEvents')->once()->andReturn($this->paginate([$event]));
        $this->productFilterService->shouldReceive('filter')->once()->andReturn(collect());

        $result = $this->handler->handle($this->dto());

        $this->assertTrue($result->items()[0]->getProductsSoldOut());
    }

    public function test_an_event_with_an_available_public_product_is_not_flagged_sold_out(): void
    {
        $event = $this->eventWithProducts([
            $this->product(quantityAvailable: 0, hideWhenSoldOut: true),
            $this->product(quantityAvailable: 3),
        ]);

        $this->eventRepository->shouldReceive('findEvents')->once()->andReturn($this->paginate([$event]));
        $this->productFilterService->shouldReceive('filter')->once()->andReturn(collect());

        $result = $this->handler->handle($this->dto());

        $this->assertFalse($result->items()[0]->getProductsSoldOut());
    }

    public function test_products_in_hidden_categories_do_not_keep_an_event_off_sale(): void
    {
        $event = $this->eventWithProducts([$this->product(quantityAvailable: 0, hideWhenSoldOut: true)]);
        $hiddenCategory = (new ProductCategoryDomainObject)->setIsHidden(true);
        $hiddenCategory->setProducts(collect([$this->product(quantityAvailable: 5)]));
        $event->getProductCategories()->push($hiddenCategory);

        $this->eventRepository->shouldReceive('findEvents')->once()->andReturn($this->paginate([$event]));
        $this->productFilterService->shouldReceive('filter')->once()->andReturn(collect());

        $result = $this->handler->handle($this->dto());

        $this->assertTrue($result->items()[0]->getProductsSoldOut());
    }

    public function test_quantities_for_every_on_sale_event_are_fetched_in_one_batch(): void
    {
        $first = $this->eventWithProducts([$this->product(quantityAvailable: 1)])->setId(1);
        $second = $this->eventWithProducts([$this->product(quantityAvailable: 1)])->setId(2);
        $ended = $this->eventWithProducts([$this->product(quantityAvailable: 1)])
            ->setId(3)
            ->setLifecycleStatus(EventLifecycleStatus::ENDED->name);

        $this->eventRepository->shouldReceive('findEvents')->once()->andReturn($this->paginate([$first, $second, $ended]));
        $this->productFilterService->shouldReceive('filter')->twice()->andReturn(collect());
        $this->eventSeatMapLookup->shouldReceive('findSummariesForEvents')->once()->with([1, 2])->andReturn([]);

        $this->handler->handle($this->dto());

        $this->assertSame([[1, 2]], $this->quantityFetches);
    }

    public function test_ended_events_expose_no_products(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setLifecycleStatus(EventLifecycleStatus::ENDED->name)
            ->setProductCategories(collect([new ProductCategoryDomainObject]));

        $this->eventRepository->shouldReceive('findEvents')->once()->andReturn($this->paginate([$event]));
        $this->productFilterService->shouldNotReceive('filter');

        $result = $this->handler->handle($this->dto());

        $this->assertTrue($result->items()[0]->getProductCategories()->isEmpty());
    }

    public function test_the_organizers_own_view_is_filtered_too(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setLifecycleStatus(EventLifecycleStatus::UPCOMING->name)
            ->setProductCategories(collect([new ProductCategoryDomainObject]));

        $this->eventRepository->shouldReceive('findEventsForOrganizer')->once()->andReturn($this->paginate([$event]));
        $this->productFilterService->shouldReceive('filter')->once()->andReturn(collect());

        $result = $this->handler->handle($this->dto(authenticatedAccountId: 10));

        $this->assertTrue($result->items()[0]->getProductCategories()->isEmpty());
    }

    private function eventWithProducts(array $products): EventDomainObject
    {
        $category = new ProductCategoryDomainObject;
        $category->setProducts(collect($products));

        return (new EventDomainObject)
            ->setId(1)
            ->setLifecycleStatus(EventLifecycleStatus::UPCOMING->name)
            ->setProductCategories(collect([$category]));
    }

    private function product(int $quantityAvailable, bool $hideWhenSoldOut = false, bool $isHidden = false): ProductDomainObject
    {
        return (new ProductDomainObject)
            ->setHideWhenSoldOut($hideWhenSoldOut)
            ->setIsHidden($isHidden)
            ->setIsHiddenWithoutPromoCode(false)
            ->setIsAddonOnly(false)
            ->setProductPrices(collect([(new ProductPriceDomainObject)->setQuantityAvailable($quantityAvailable)]));
    }

    private function dto(?int $authenticatedAccountId = null): GetPublicOrganizerEventsDTO
    {
        return new GetPublicOrganizerEventsDTO(
            organizerId: 1,
            queryParams: new QueryParamsDTO,
            authenticatedAccountId: $authenticatedAccountId,
        );
    }

    private function paginate(array $events): LengthAwarePaginator
    {
        return new LengthAwarePaginator($events, count($events), 25);
    }
}
