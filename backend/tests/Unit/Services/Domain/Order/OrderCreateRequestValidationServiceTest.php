<?php

namespace Tests\Unit\Services\Domain\Order;

use Carbon\Carbon;
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
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Order\OrderCreateRequestValidationService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Product\ProductPriceService;
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

    private ProductPriceService|MockInterface $productPriceService;

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
        $this->productPriceService = Mockery::mock(ProductPriceService::class);

        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->byDefault()
            ->andReturn(collect());

        $this->orderItemRepository
            ->shouldReceive('getReservedQuantityForOccurrence')
            ->byDefault()
            ->andReturn(0);

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->byDefault()
            ->andReturn(collect([1]));

        $this->occurrenceRepository
            ->shouldReceive('countWhere')
            ->byDefault()
            ->andReturn(1);

        $this->productRepository
            ->shouldReceive('loadRelation')
            ->byDefault()
            ->andReturnSelf();

        $this->productRepository
            ->shouldReceive('findWhereIn')
            ->byDefault()
            ->andReturnUsing(fn ($field, $ids) => collect($ids)
                ->map(fn ($id) => $this->createTicketProductStub((int) $id)));

        $eligibilityService = new OccurrencePurchaseEligibilityService(
            $this->occurrenceRepository,
            $this->orderItemRepository,
            $this->visibilityRepository,
        );

        $this->service = new OrderCreateRequestValidationService(
            $this->productRepository,
            $this->promoCodeRepository,
            $this->eventRepository,
            $this->occurrenceRepository,
            $this->availabilityService,
            $eligibilityService,
            $this->productPriceService,
        );
    }

    public function test_rejects_cancelled_occurrence(): void
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

    public function test_rejects_sold_out_occurrence(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('sold out');

        $occurrence = $this->createOccurrence(
            capacity: 10,
            usedCapacity: 10,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);

        $this->service->validateRequestData(1, $this->createRequestData(10));
    }

    public function test_rejects_when_occurrence_capacity_exceeded(): void
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

    public function test_general_products_do_not_consume_occurrence_capacity(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 10,
            usedCapacity: 8,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100, productType: 'GENERAL');

        $data = $this->createRequestData(10, quantity: 5);

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    public function test_accepts_active_occurrence_with_sufficient_capacity(): void
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

    public function test_normalizes_missing_occurrence_id_for_single_event_checkout(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 100,
            usedCapacity: 0,
        );

        $this->setupEventLookup(1, isRecurring: false);
        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->andReturn(collect([$occurrence]));
        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100);

        $data = $this->createRequestData(10, quantity: 2);
        unset($data['products'][0]['event_occurrence_id']);

        $normalized = $this->service->validateRequestData(1, $data);

        $this->assertSame(10, $normalized['products'][0]['event_occurrence_id']);
    }

    public function test_requires_occurrence_id_for_recurring_event_checkout(): void
    {
        $this->setupEventLookup(1, isRecurring: true);

        $data = $this->createRequestData(10);
        unset($data['products'][0]['event_occurrence_id']);

        try {
            $this->service->validateRequestData(1, $data);
            $this->fail('Expected ValidationException for missing event_occurrence_id');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('products.0.event_occurrence_id', $exception->errors());
        }
    }

    public function test_accepts_occurrence_with_unlimited_capacity(): void
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

    public function test_rejects_when_occurrence_not_found_for_event(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not found');

        $this->setupOccurrenceLookup(1, 999, null);
        $this->setupEventLookup(1);

        $this->service->validateRequestData(1, $this->createRequestData(999));
    }

    public function test_skips_capacity_assignments_for_recurring_events(): void
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

    public function test_rejects_product_hidden_from_occurrence(): void
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

        $visibilityRule = (new ProductOccurrenceVisibilityDomainObject)
            ->setEventOccurrenceId(10)
            ->setProductId(99);

        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->with('event_occurrence_id', [10])
            ->andReturn(collect([$visibilityRule]));

        $this->service->validateRequestData(1, $this->createRequestData(10));
    }

    public function test_allows_product_explicitly_visible_on_occurrence(): void
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

        $visibilityRule = (new ProductOccurrenceVisibilityDomainObject)
            ->setEventOccurrenceId(10)
            ->setProductId(10);

        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->with('event_occurrence_id', [10])
            ->andReturn(collect([$visibilityRule]));

        $this->service->validateRequestData(1, $this->createRequestData(10));
        $this->assertTrue(true);
    }

    public function test_enforces_per_occurrence_visibility_for_multi_occurrence_order(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not available for this occurrence');

        $occurrence10 = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 100,
        );

        $occurrence20 = (new EventOccurrenceDomainObject)
            ->setId(20)
            ->setEventId(1)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setCapacity(100)
            ->setUsedCapacity(0)
            ->setStartDate(Carbon::now()->addMonths(2)->toDateTimeString());

        $this->setupOccurrenceLookup(1, 10, $occurrence10);
        $this->setupOccurrenceLookup(1, 20, $occurrence20);
        $this->setupEventLookup(1);

        $rule10 = (new ProductOccurrenceVisibilityDomainObject)
            ->setEventOccurrenceId(10)
            ->setProductId(10);
        $rule20 = (new ProductOccurrenceVisibilityDomainObject)
            ->setEventOccurrenceId(20)
            ->setProductId(99);

        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->with('event_occurrence_id', [10])
            ->andReturn(collect([$rule10]));
        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->with('event_occurrence_id', [20])
            ->andReturn(collect([$rule20]));

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

    public function test_allows_all_products_when_no_visibility_rules(): void
    {
        $occurrence10 = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 100,
        );
        $occurrence20 = (new EventOccurrenceDomainObject)
            ->setId(20)
            ->setEventId(1)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setCapacity(100)
            ->setUsedCapacity(0)
            ->setStartDate(Carbon::now()->addMonths(3)->toDateTimeString());

        $this->setupOccurrenceLookup(1, 10, $occurrence10);
        $this->setupOccurrenceLookup(1, 20, $occurrence20);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100);

        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->with('event_occurrence_id', [10])
            ->andReturn(collect());
        $this->visibilityRepository
            ->shouldReceive('findWhereIn')
            ->with('event_occurrence_id', [20])
            ->andReturn(collect());

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

    public function test_rejects_donation_below_occurrence_override_floor(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100, type: 'DONATION');

        $this->productPriceService
            ->shouldReceive('getDonationMinimumPrice')
            ->with(Mockery::type(ProductDomainObject::class), 100, 10)
            ->andReturn(25.0);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('minimum amount');

        $this->service->validateRequestData(1, $this->createRequestData(10, price: 15.0));
    }

    public function test_accepts_donation_at_effective_floor(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100, type: 'DONATION');

        $this->productPriceService
            ->shouldReceive('getDonationMinimumPrice')
            ->with(Mockery::type(ProductDomainObject::class), 100, 10)
            ->andReturn(25.0);

        $this->service->validateRequestData(1, $this->createRequestData(10, price: 25.0));
        $this->assertTrue(true);
    }

    public function test_accepts_general_only_cart_on_sold_out_occurrence(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 10,
            usedCapacity: 10,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100, productType: 'GENERAL');

        $this->service->validateRequestData(1, $this->createRequestData(10, quantity: 2));
        $this->assertTrue(true);
    }

    public function test_rejects_duplicate_product_price_lines_exceeding_available_stock(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1, available: 4);
        $this->setupProducts(1, 10, 100);

        $data = [
            'products' => [
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 3]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 3]],
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('maximum number of products available');

        $this->service->validateRequestData(1, $data);
    }

    public function test_accepts_duplicate_product_price_lines_within_available_stock(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100);

        $data = [
            'products' => [
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 3]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 3]],
                ],
            ],
        ];

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    public function test_rejects_same_price_across_occurrences_exceeding_available_stock(): void
    {
        $occurrence10 = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
        );

        $occurrence20 = (new EventOccurrenceDomainObject)
            ->setId(20)
            ->setEventId(1)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setCapacity(null)
            ->setUsedCapacity(0)
            ->setStartDate(Carbon::now()->addMonths(2)->toDateTimeString());

        $this->setupOccurrenceLookup(1, 10, $occurrence10);
        $this->setupOccurrenceLookup(1, 20, $occurrence20);
        $this->setupEventLookup(1, isRecurring: true);
        $this->setupAvailability(1, available: 4);
        $this->setupProducts(1, 10, 100);

        $data = [
            'products' => [
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 3]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 20,
                    'quantities' => [['price_id' => 100, 'quantity' => 3]],
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('maximum number of products available');

        $this->service->validateRequestData(1, $data);
    }

    public function test_rejects_duplicate_lines_exceeding_max_per_order(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100, maxPerOrder: 10);

        $data = [
            'products' => [
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 6]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 6]],
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('maximum number of products available for Test Product is 10');

        $this->service->validateRequestData(1, $data);
    }

    public function test_accepts_duplicate_lines_within_max_per_order(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100, maxPerOrder: 10);

        $data = [
            'products' => [
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 5]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 5]],
                ],
            ],
        ];

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    public function test_accepts_duplicate_lines_combining_to_meet_min_per_order(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100, minPerOrder: 5);

        $data = [
            'products' => [
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 3]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 2]],
                ],
            ],
        ];

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    public function test_rejects_duplicate_lines_combining_below_min_per_order(): void
    {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100, minPerOrder: 5);

        $data = [
            'products' => [
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 2]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 2]],
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must order at least');

        $this->service->validateRequestData(1, $data);
    }

    public function test_max_per_order_applies_per_occurrence_for_recurring_events(): void
    {
        $occurrence10 = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
        );

        $occurrence20 = (new EventOccurrenceDomainObject)
            ->setId(20)
            ->setEventId(1)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setCapacity(null)
            ->setUsedCapacity(0)
            ->setStartDate(Carbon::now()->addMonths(2)->toDateTimeString());

        $this->setupOccurrenceLookup(1, 10, $occurrence10);
        $this->setupOccurrenceLookup(1, 20, $occurrence20);
        $this->setupEventLookup(1, isRecurring: true);
        $this->setupAvailability(1);
        $this->setupProducts(1, 10, 100, maxPerOrder: 10);

        $data = [
            'products' => [
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 6]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 20,
                    'quantities' => [['price_id' => 100, 'quantity' => 6]],
                ],
            ],
        ];

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    public function test_rejects_addon_only_product_without_parent_in_order(): void
    {
        $this->setupEventLookup(1);

        $addon = $this->createFullProductMock(productId: 10, priceId: 100, isAddonOnly: true, productType: 'GENERAL');

        $this->productRepository
            ->shouldReceive('findWhereIn')
            ->andReturn(collect([$addon]));

        $this->productRepository
            ->shouldReceive('findParentProductIds')
            ->with([10])
            ->andReturn(collect([10 => [99]]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('add-on');

        $this->service->validateRequestData(1, $this->createRequestData(10));
    }

    public function test_allows_addon_only_product_with_parent_in_order(): void
    {
        $occurrence = $this->createOccurrence();
        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailabilityFor([[9, 90], [10, 100]]);

        $parent = $this->createFullProductMock(productId: 9, priceId: 90);
        $addon = $this->createFullProductMock(productId: 10, priceId: 100, isAddonOnly: true, productType: 'GENERAL');

        $this->productRepository
            ->shouldReceive('findWhereIn')
            ->andReturn(collect([$parent, $addon]));

        $this->productRepository
            ->shouldReceive('findParentProductIds')
            ->with([10])
            ->andReturn(collect([10 => [9]]));

        $data = [
            'products' => [
                [
                    'product_id' => 9,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 90, 'quantity' => 1]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 2]],
                ],
            ],
        ];

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    public function test_ignores_addon_only_product_with_zero_quantity(): void
    {
        $occurrence = $this->createOccurrence();
        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailabilityFor([[9, 90], [10, 100]]);

        $parent = $this->createFullProductMock(productId: 9, priceId: 90);
        $addon = $this->createFullProductMock(productId: 10, priceId: 100, isAddonOnly: true, productType: 'GENERAL');

        $this->productRepository
            ->shouldReceive('findWhereIn')
            ->andReturn(collect([$parent, $addon]));

        $this->productRepository->shouldNotReceive('findParentProductIds');

        $data = [
            'products' => [
                [
                    'product_id' => 9,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 90, 'quantity' => 1]],
                ],
                [
                    'product_id' => 10,
                    'event_occurrence_id' => 10,
                    'quantities' => [['price_id' => 100, 'quantity' => 0]],
                ],
            ],
        ];

        $this->service->validateRequestData(1, $data);
        $this->assertTrue(true);
    }

    public function test_rejects_product_before_its_sale_start_date(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('is not yet on sale');

        $this->setupSaleWindowScenario(productBeforeSaleStart: true);

        $this->service->validateRequestData(1, $this->createRequestData(10));
    }

    public function test_rejects_product_after_its_sale_end_date(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Sales for');

        $this->setupSaleWindowScenario(productAfterSaleEnd: true);

        $this->service->validateRequestData(1, $this->createRequestData(10));
    }

    public function test_rejects_price_tier_after_its_sale_end_date(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid price ID');

        $this->setupSaleWindowScenario(priceAfterSaleEnd: true);

        $this->service->validateRequestData(1, $this->createRequestData(10));
    }

    public function test_rejects_price_tier_before_its_sale_start_date(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid price ID');

        $this->setupSaleWindowScenario(priceBeforeSaleStart: true);

        $this->service->validateRequestData(1, $this->createRequestData(10));
    }

    public function test_accepts_product_inside_its_sale_window(): void
    {
        $this->setupSaleWindowScenario();

        $this->service->validateRequestData(1, $this->createRequestData(10));

        $this->assertTrue(true);
    }

    private function setupSaleWindowScenario(
        bool $productBeforeSaleStart = false,
        bool $productAfterSaleEnd = false,
        bool $priceBeforeSaleStart = false,
        bool $priceAfterSaleEnd = false,
    ): void {
        $occurrence = $this->createOccurrence(
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 100,
            usedCapacity: 0,
        );

        $this->setupOccurrenceLookup(1, 10, $occurrence);
        $this->setupEventLookup(1);
        $this->setupAvailability(1);
        $this->setupProducts(
            1,
            10,
            100,
            productBeforeSaleStart: $productBeforeSaleStart,
            productAfterSaleEnd: $productAfterSaleEnd,
            priceBeforeSaleStart: $priceBeforeSaleStart,
            priceAfterSaleEnd: $priceAfterSaleEnd,
        );
    }

    private function createTicketProductStub(int $id): ProductDomainObject|MockInterface
    {
        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn($id);
        $product->shouldReceive('getProductType')->andReturn('TICKET');
        $product->shouldReceive('getIsAddonOnly')->andReturn(false);

        return $product;
    }

    private function createOccurrence(
        string $status = 'ACTIVE',
        ?int $capacity = null,
        int $usedCapacity = 0,
    ): EventOccurrenceDomainObject {
        return (new EventOccurrenceDomainObject)
            ->setId(10)
            ->setEventId(1)
            ->setStatus($status)
            ->setCapacity($capacity)
            ->setUsedCapacity($usedCapacity)
            ->setStartDate(Carbon::now()->addMonth()->toDateTimeString());
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
        $event->shouldReceive('getCurrency')->andReturn('USD');

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
                        'product_type' => 'TICKET',
                        'price_label' => null,
                        'quantity_available' => $available,
                        'quantity_reserved' => 0,
                        'initial_quantity_available' => 100,
                        'capacities' => new Collection,
                    ]),
                ]),
                capacities: $capacities ?? collect(),
            ));
    }

    private function setupProducts(
        int $eventId,
        int $productId,
        int $priceId,
        string $productType = 'TICKET',
        string $type = 'PAID',
        int $maxPerOrder = 10,
        int $minPerOrder = 1,
        bool $productBeforeSaleStart = false,
        bool $productAfterSaleEnd = false,
        bool $priceBeforeSaleStart = false,
        bool $priceAfterSaleEnd = false,
    ): void {
        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getId')->andReturn($priceId);
        $price->shouldReceive('getIsHidden')->andReturn(false);
        $price->shouldReceive('getLabel')->andReturn(null);
        $price->shouldReceive('isBeforeSaleStartDate')->andReturn($priceBeforeSaleStart);
        $price->shouldReceive('isAfterSaleEndDate')->andReturn($priceAfterSaleEnd);

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn($productId);
        $product->shouldReceive('getEventId')->andReturn($eventId);
        $product->shouldReceive('getTitle')->andReturn('Test Product');
        $product->shouldReceive('getMaxPerOrder')->andReturn($maxPerOrder);
        $product->shouldReceive('getMinPerOrder')->andReturn($minPerOrder);
        $product->shouldReceive('getType')->andReturn($type);
        $product->shouldReceive('getPrice')->andReturn(10.0);
        $product->shouldReceive('isSoldOut')->andReturn(false);
        $product->shouldReceive('getProductPrices')->andReturn(collect([$price]));
        $product->shouldReceive('getProductType')->andReturn($productType);
        $product->shouldReceive('getIsHidden')->andReturn(false);
        $product->shouldReceive('getIsHiddenWithoutPromoCode')->andReturn(false);
        $product->shouldReceive('isBeforeSaleStartDate')->andReturn($productBeforeSaleStart);
        $product->shouldReceive('isAfterSaleEndDate')->andReturn($productAfterSaleEnd);
        $product->shouldReceive('getIsAddonOnly')->andReturn(false);

        $this->productRepository
            ->shouldReceive('loadRelation')->andReturnSelf();

        $this->productRepository
            ->shouldReceive('findWhereIn')
            ->andReturn(collect([$product]));
    }

    private function createFullProductMock(
        int $productId,
        int $priceId,
        bool $isAddonOnly = false,
        string $productType = 'TICKET',
    ): ProductDomainObject|MockInterface {
        $price = Mockery::mock(ProductPriceDomainObject::class);
        $price->shouldReceive('getId')->andReturn($priceId);
        $price->shouldReceive('getIsHidden')->andReturn(false);
        $price->shouldReceive('getLabel')->andReturn(null);
        $price->shouldReceive('isBeforeSaleStartDate')->andReturn(false);
        $price->shouldReceive('isAfterSaleEndDate')->andReturn(false);

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn($productId);
        $product->shouldReceive('getEventId')->andReturn(1);
        $product->shouldReceive('getTitle')->andReturn('Product '.$productId);
        $product->shouldReceive('getMaxPerOrder')->andReturn(10);
        $product->shouldReceive('getMinPerOrder')->andReturn(1);
        $product->shouldReceive('getType')->andReturn('PAID');
        $product->shouldReceive('isSoldOut')->andReturn(false);
        $product->shouldReceive('getProductPrices')->andReturn(collect([$price]));
        $product->shouldReceive('getProductType')->andReturn($productType);
        $product->shouldReceive('getIsHidden')->andReturn(false);
        $product->shouldReceive('getIsHiddenWithoutPromoCode')->andReturn(false);
        $product->shouldReceive('isBeforeSaleStartDate')->andReturn(false);
        $product->shouldReceive('isAfterSaleEndDate')->andReturn(false);
        $product->shouldReceive('getIsAddonOnly')->andReturn($isAddonOnly);

        return $product;
    }

    private function setupAvailabilityFor(array $productPricePairs): void
    {
        $this->availabilityService
            ->shouldReceive('getAvailableProductQuantities')
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect($productPricePairs)->map(
                    fn (array $pair) => AvailableProductQuantitiesDTO::fromArray([
                        'product_id' => $pair[0],
                        'price_id' => $pair[1],
                        'product_title' => 'Product '.$pair[0],
                        'product_type' => 'TICKET',
                        'price_label' => null,
                        'quantity_available' => 100,
                        'quantity_reserved' => 0,
                        'initial_quantity_available' => 100,
                        'capacities' => new Collection,
                    ])
                ),
                capacities: collect(),
            ));
    }

    private function createRequestData(int $occurrenceId, int $productId = 10, int $priceId = 100, int $quantity = 1, ?float $price = null): array
    {
        $quantityData = [
            'price_id' => $priceId,
            'quantity' => $quantity,
        ];

        if ($price !== null) {
            $quantityData['price'] = $price;
        }

        return [
            'products' => [
                [
                    'product_id' => $productId,
                    'event_occurrence_id' => $occurrenceId,
                    'quantities' => [$quantityData],
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
