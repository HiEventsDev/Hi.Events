<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
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
    private OrderCreateRequestValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->availabilityService = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);

        $this->service = new OrderCreateRequestValidationService(
            $this->productRepository,
            $this->promoCodeRepository,
            $this->eventRepository,
            $this->availabilityService,
            $this->occurrenceRepository,
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
