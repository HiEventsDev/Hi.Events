<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductOccurrenceVisibilityDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderCreateRequestValidationService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderCreateRequestValidationServiceTest extends TestCase
{
    private ProductRepositoryInterface|MockInterface $productRepository;
    private PromoCodeRepositoryInterface|MockInterface $promoCodeRepository;
    private EventRepositoryInterface|MockInterface $eventRepository;
    private AvailableProductQuantitiesFetchService|MockInterface $availabilityService;
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;
    private ProductOccurrenceVisibilityRepositoryInterface|MockInterface $visibilityRepository;
    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;
    private OrderCreateRequestValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->availabilityService = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->visibilityRepository = Mockery::mock(ProductOccurrenceVisibilityRepositoryInterface::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);

        // Default: no visibility rules → all products visible. Individual tests can
        // override this expectation when they want to exercise the visibility check.
        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->byDefault()
            ->andReturn(collect());

        // Default: no reserved orders. Tests that exercise capacity-vs-reservation
        // logic can override this expectation.
        $this->orderItemRepository
            ->shouldReceive('getReservedQuantityForOccurrence')
            ->byDefault()
            ->andReturn(0);

        $this->service = new OrderCreateRequestValidationService(
            $this->productRepository,
            $this->promoCodeRepository,
            $this->eventRepository,
            $this->availabilityService,
            $this->occurrenceRepository,
            $this->visibilityRepository,
            $this->orderItemRepository,
        );
    }

    public function testRejectsCancelledOccurrence(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cancelled');

        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::CANCELLED->name,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);

        $this->service->validateRequestData(1, $this->createRequestData(10));
    }

    public function testRejectsSoldOutOccurrence(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('sold out');

        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::SOLD_OUT->name,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);

        $this->service->validateRequestData(1, $this->createRequestData(10));
    }

    public function testRejectsWhenOccurrenceCapacityExceeded(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('capacity');

        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 10,
            usedCapacity: 8,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);

        $data = $this->createRequestData(10, quantity: 5);

        $this->service->validateRequestData(1, $data);
    }

    public function testAcceptsActiveOccurrenceWithSufficientCapacity(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 100,
            usedCapacity: 0,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100);

        $data = $this->createRequestData(10, quantity: 2);

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    public function testAcceptsOccurrenceWithUnlimitedCapacity(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: null,
            usedCapacity: 0,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100);

        $data = $this->createRequestData(10, quantity: 5);

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    public function testRejectsWhenOccurrenceNotFoundForEvent(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not found');

        $this->setupOccurrenceLookup(1, 999, null);
        $this->setupEventLookup(1);

        $this->service->validateRequestData(1, $this->createRequestData(999));
    }

    public function testSkipsCapacityAssignmentsForRecurringEvents(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: null,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1, isRecurring: true);
        $this->setupAvailability(1, capacities: collect());
        $this->setupProducts(1, 10, 100);

        $data = $this->createRequestData(10, quantity: 2);

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    public function testRejectsProductHiddenFromOccurrence(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not available for this occurrence');

        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 100,
            usedCapacity: 0,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);

        // Visibility rules exist for occurrence 10 but product 10 is NOT in the visible set,
        // so the order must be rejected even though all other validation would pass.
        $visibilityRule = (new ProductOccurrenceVisibilityDomainObject())
            ->setEventOccurrenceId(10)
            ->setProductId(99);

        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->with('event_occurrence_id', [10])
            ->andReturn(collect([$visibilityRule]));

        $this->service->validateRequestData(1, $this->createRequestData(10));
    }

    public function testAllowsProductExplicitlyVisibleOnOccurrence(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 100,
            usedCapacity: 0,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100);

        $visibilityRule = (new ProductOccurrenceVisibilityDomainObject())
            ->setEventOccurrenceId(10)
            ->setProductId(10);

        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->with('event_occurrence_id', [10])
            ->andReturn(collect([$visibilityRule]));

        $this->service->validateRequestData(1, $this->createRequestData(10));
        $this->assertTrue(true);
    }

    /**
     * Regression guard for the perf fix: an order that spans multiple occurrences must
     * resolve all visibility rules in a single batched query (findWhereIn) instead of
     * one query per occurrence (the original N+1 implementation).
     */
    public function testBatchesVisibilityLookupForMultiOccurrenceOrder(): void
    {
        // Cart contains product 10 on occurrence 10 and product 20 on occurrence 20.
        // Visibility allows product 10 on occurrence 10 but blocks product 20 on
        // occurrence 20 — so processing reaches the second occurrence and throws.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not available for this occurrence');

        $occurrence10 = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 100,
        );

        $occurrence20 = (new EventOccurrenceDomainObject())
            ->setId(20)
            ->setEventId(1)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setCapacity(100)
            ->setUsedCapacity(0)
            ->setStartDate('2026-07-15 10:00:00');

        $this->setupOccurrenceLookup(1, 10, $occurrence10);
        $this->setupOccurrenceLookup(1, 20, $occurrence20);
        $this->setupEventLookup(1);

        // Visibility rules cover BOTH occurrences. The key assertion is that
        // findWhereIn is called exactly ONCE (not once per occurrence) with both ids.
        $rule10 = (new ProductOccurrenceVisibilityDomainObject())
            ->setEventOccurrenceId(10)
            ->setProductId(10);
        $rule20 = (new ProductOccurrenceVisibilityDomainObject())
            ->setEventOccurrenceId(20)
            ->setProductId(99);

        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->once()
            ->with('event_occurrence_id', [10, 20])
            ->andReturn(collect([$rule10, $rule20]));

        $data = [
            'products' => [
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 1]],
                ],
                [
                    'product_id' => 20,
                    'event_occurrence_id' => 20,
                    'quantities' => [['price_id' => 200, 'quantity' => 1]],
                ],
            ],
        ];

        $this->service->validateRequestData(1, $data);
    }

    /**
     * Verifies the perf fix: when an order has many distinct occurrences, the visibility
     * lookup MUST happen exactly once. Previously this was N visibility queries inside
     * the per-occurrence loop. With no rules in the response, all products pass through.
     */
    public function testBatchesVisibilityLookupAndAllowsAllWhenNoRules(): void
    {
        $occurrence10 = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 100,
        );
        $occurrence20 = (new EventOccurrenceDomainObject())
            ->setId(20)
            ->setEventId(1)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setCapacity(100)
            ->setUsedCapacity(0)
            ->setStartDate('2026-08-01 10:00:00');

        $this->setupOccurrenceLookup(1, 10, $occurrence10);
        $this->setupOccurrenceLookup(1, 20, $occurrence20);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100);

        // Override the default empty findWhereIn stub with an explicit `once()` so the
        // perf fix is provably exercised — exactly one batched lookup, both ids, no
        // rules returned (default-visible).
        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->once()
            ->with('event_occurrence_id', [10, 20])
            ->andReturn(collect());

        // Product 10 sells on both occurrences in this scenario; product details are
        // identical so the existing single-product setup is sufficient.
        $data = [
            'products' => [
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 1]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 20,
                    'quantities' => [['price_id' => 100, 'quantity' => 1]],
                ],
            ],
        ];

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    private function createOccurrence(
        string $status = 'ACTIVE',
        ?int   $capacity = null,
        int    $usedCapacity = 0,
    ): EventOccurrenceDomainObject
    {
        return (new EventOccurrenceDomainObject())
            ->setId(10)
            ->setEventId(1)
            ->setStatus($status)
            ->setCapacity($capacity)
            ->setUsedCapacity($usedCapacity)
            ->setStartDate('2026-06-15 10:00:00');
    }

    private function setupOccurrenceLookup(int $eventId, int $occurrenceId, ?EventOccurrenceDomainObject $occurrence): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'id' => $occurrenceId,
                'event_id' => $eventId,
            ])
            ->andReturn($occurrence);
    }

    private function setupEventLookup(int $eventId, bool $isRecurring = false): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getId')->andReturn($eventId);
        $event->shouldReceive('isRecurring')->andReturn($isRecurring);

        $this->eventRepository
            ->shouldReceive('findById')
            ->with($eventId)
            ->andReturn($event);
    }

    private function setupAvailability(int $eventId, ?Collection $capacities = null, int $available = 100): void
    {
        $this->availabilityService
            ->shouldReceive('getAvailableProductQuantities')
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    AvailableProductQuantitiesDTO::fromArray([
                        'product_id' => 10,
                        'price_id' => 100,
                        'product_title' => 'Test Product',
                        'price_label' => null,
                        'quantity_available' => $available,
                        'quantity_reserved' => 0,
                        'initial_quantity_available' => 100,
                        'capacities' => new Collection(),
                    ]),
                ]),
                capacities: $capacities ?? collect(),
            ));
    }

    private function setupProducts(int $eventId, int $productId, int $priceId): void
    {
        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getId')->andReturn($priceId);

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn($productId);
        $product->shouldReceive('getEventId')->andReturn($eventId);
        $product->shouldReceive('getTitle')->andReturn('Test Product');
        $product->shouldReceive('getMaxPerOrder')->andReturn(10);
        $product->shouldReceive('getMinPerOrder')->andReturn(1);
        $product->shouldReceive('getType')->andReturn('PAID');
        $product->shouldReceive('getPrice')->andReturn(10.0);
        $product->shouldReceive('isSoldOut')->andReturn(false);
        $product->shouldReceive('getProductPrices')->andReturn(collect([$price]));
        $product->shouldReceive('getProductType')->andReturn('TICKET');

        $this->productRepository
            ->shouldReceive('loadRelation')->andReturnSelf();

        $this->productRepository
            ->shouldReceive('findWhereIn')
            ->andReturn(collect([$product]));
    }

    private function createRequestData(int $occurrenceId, int $productId = 10, int $priceId = 100, int $quantity = 1): array
    {
        return [
            'products' => [
                [
                    'product_id' => $productId,
                    'event_occurrence_id' => $occurrenceId,
                    'quantities' => [
                        [
                            'price_id' => $priceId,
                            'quantity' => $quantity,
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
