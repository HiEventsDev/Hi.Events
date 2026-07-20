<?php

namespace Tests\Unit\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\CreateOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\PromoCode\PromoCodeUsageValidationService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateOrderHandlerTest extends TestCase
{
    private EventRepositoryInterface|MockInterface $eventRepository;

    private PromoCodeRepositoryInterface|MockInterface $promoCodeRepository;

    private PromoCodeUsageValidationService|MockInterface $promoCodeUsageValidationService;

    private AffiliateRepositoryInterface|MockInterface $affiliateRepository;

    private OrderManagementService|MockInterface $orderManagementService;

    private OrderItemProcessingService|MockInterface $orderItemProcessingService;

    private AvailableProductQuantitiesFetchService|MockInterface $availabilityService;

    private OccurrencePurchaseEligibilityService|MockInterface $occurrenceEligibilityService;

    private DatabaseManager|MockInterface $databaseManager;

    private CreateOrderHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $this->promoCodeUsageValidationService = Mockery::mock(PromoCodeUsageValidationService::class);
        $this->affiliateRepository = Mockery::mock(AffiliateRepositoryInterface::class);
        $this->orderManagementService = Mockery::mock(OrderManagementService::class);
        $this->orderItemProcessingService = Mockery::mock(OrderItemProcessingService::class);
        $this->availabilityService = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $this->occurrenceEligibilityService = Mockery::mock(OccurrencePurchaseEligibilityService::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->occurrenceEligibilityService->shouldReceive('assertOccurrencePurchasable')
            ->byDefault()
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new CreateOrderHandler(
            $this->eventRepository,
            $this->promoCodeRepository,
            $this->promoCodeUsageValidationService,
            $this->affiliateRepository,
            $this->orderManagementService,
            $this->orderItemProcessingService,
            $this->availabilityService,
            $this->occurrenceEligibilityService,
            $this->databaseManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_acquires_advisory_lock_before_creating_order(): void
    {
        $eventId = 42;

        $this->databaseManager->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?)', [$eventId])
            ->andReturn(true);

        $this->setupSuccessfulOrderCreation($eventId);

        $result = $this->handler->handle($eventId, $this->createOrderDTO());
        $this->assertInstanceOf(OrderDomainObject::class, $result);
    }

    public function test_throws_when_product_quantity_exceeds_availability(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);

        $this->setupEventMock($eventId);
        $this->orderManagementService->shouldReceive('deleteExistingOrders');

        $this->availabilityService->shouldReceive('getAvailableProductQuantities')
            ->with($eventId, true, Mockery::any())
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    $this->createAvailabilityDTO(10, 100, 2),
                ]),
            ));

        $dto = $this->createOrderDTO(quantity: 5);

        $this->expectException(ValidationException::class);
        $this->handler->handle($eventId, $dto);
    }

    public function test_passes_when_quantity_is_within_availability(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);
        $this->setupSuccessfulOrderCreation($eventId, productId: 10, priceId: 100, available: 5);

        $dto = $this->createOrderDTO(quantity: 2);

        $result = $this->handler->handle($eventId, $dto);
        $this->assertInstanceOf(OrderDomainObject::class, $result);
    }

    public function test_skips_zero_quantity_products(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);
        $this->setupSuccessfulOrderCreation($eventId, available: 0);

        $dto = $this->createOrderDTO(quantity: 0);

        $result = $this->handler->handle($eventId, $dto);
        $this->assertInstanceOf(OrderDomainObject::class, $result);
    }

    public function test_aggregates_ticket_quantities_per_occurrence_using_preloaded_occurrence_data(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);
        $this->setupEventMock($eventId);
        $this->orderManagementService->shouldReceive('deleteExistingOrders');

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceEligibilityService->shouldReceive('assertOccurrencePurchasable')
            ->once()
            ->with($eventId, 1, 6, false, $occurrence, 4)
            ->andReturn($occurrence);

        $this->availabilityService->shouldReceive('getAvailableProductQuantities')
            ->with($eventId, true, Mockery::any())
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    $this->createAvailabilityDTO(10, 100, 100),
                    $this->createAvailabilityDTO(11, 101, 100),
                    $this->createAvailabilityDTO(12, 102, 100, ProductType::GENERAL->name),
                ]),
                capacities: collect(),
                occurrence: $occurrence,
                occurrenceReservedQuantity: 4,
            ));

        $order = Mockery::mock(OrderDomainObject::class);
        $this->orderManagementService->shouldReceive('createNewOrder')->andReturn($order);
        $this->orderItemProcessingService->shouldReceive('process')->andReturn(collect([Mockery::mock(OrderItemDomainObject::class)]));
        $this->orderManagementService->shouldReceive('updateOrderTotals')->andReturn($order);

        $dto = $this->createMultiLineOrderDTO([
            [10, 100, 3, 1],
            [11, 101, 3, 1],
            [12, 102, 5, 1],
        ]);

        $result = $this->handler->handle($eventId, $dto);
        $this->assertInstanceOf(OrderDomainObject::class, $result);
    }

    public function test_rejects_when_aggregate_occurrence_capacity_exceeded(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);
        $this->setupEventMock($eventId);
        $this->orderManagementService->shouldReceive('deleteExistingOrders');

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->availabilityService->shouldReceive('getAvailableProductQuantities')
            ->with($eventId, true, Mockery::any())
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    $this->createAvailabilityDTO(10, 100, 100),
                    $this->createAvailabilityDTO(11, 101, 100),
                ]),
                capacities: collect(),
                occurrence: $occurrence,
                occurrenceReservedQuantity: 6,
            ));

        $this->occurrenceEligibilityService->shouldReceive('assertOccurrencePurchasable')
            ->once()
            ->with($eventId, 1, 6, false, $occurrence, 6)
            ->andThrow(ValidationException::withMessages([
                'event_occurrence_id' => 'Not enough capacity available for this occurrence',
            ]));

        $this->orderManagementService->shouldNotReceive('createNewOrder');

        $dto = $this->createMultiLineOrderDTO([
            [10, 100, 3, 1],
            [11, 101, 3, 1],
        ]);

        $this->expectException(ValidationException::class);
        $this->handler->handle($eventId, $dto);
    }

    public function test_general_only_cart_asserts_occurrence_with_zero_additional_quantity(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);
        $this->setupEventMock($eventId);
        $this->orderManagementService->shouldReceive('deleteExistingOrders');

        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->availabilityService->shouldReceive('getAvailableProductQuantities')
            ->with($eventId, true, Mockery::any())
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    $this->createAvailabilityDTO(12, 102, 100, ProductType::GENERAL->name),
                ]),
                capacities: collect(),
                occurrence: $occurrence,
                occurrenceReservedQuantity: null,
            ));

        $this->occurrenceEligibilityService->shouldReceive('assertOccurrencePurchasable')
            ->once()
            ->with($eventId, 1, 0, false, $occurrence, null)
            ->andReturn($occurrence);

        $order = Mockery::mock(OrderDomainObject::class);
        $this->orderManagementService->shouldReceive('createNewOrder')->andReturn($order);
        $this->orderItemProcessingService->shouldReceive('process')->andReturn(collect([Mockery::mock(OrderItemDomainObject::class)]));
        $this->orderManagementService->shouldReceive('updateOrderTotals')->andReturn($order);

        $dto = $this->createMultiLineOrderDTO([
            [12, 102, 5, 1],
        ]);

        $result = $this->handler->handle($eventId, $dto);
        $this->assertInstanceOf(OrderDomainObject::class, $result);
    }

    public function test_rejects_duplicate_product_price_lines_exceeding_availability(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);
        $this->setupEventMock($eventId);
        $this->orderManagementService->shouldReceive('deleteExistingOrders');

        $this->availabilityService->shouldReceive('getAvailableProductQuantities')
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    $this->createAvailabilityDTO(10, 100, 4),
                ]),
            ));

        $this->orderManagementService->shouldNotReceive('createNewOrder');

        $dto = $this->createMultiLineOrderDTO([
            [10, 100, 3, 1],
            [10, 100, 3, 1],
        ]);

        $this->expectException(ValidationException::class);
        $this->handler->handle($eventId, $dto);
    }

    public function test_passes_when_duplicate_product_price_lines_fit_availability(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);
        $this->setupSuccessfulOrderCreation($eventId, productId: 10, priceId: 100, available: 6);

        $dto = $this->createMultiLineOrderDTO([
            [10, 100, 3, 1],
            [10, 100, 3, 1],
        ]);

        $result = $this->handler->handle($eventId, $dto);
        $this->assertInstanceOf(OrderDomainObject::class, $result);
    }

    public function test_rejects_same_price_across_occurrences_exceeding_total_availability(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);
        $this->setupEventMock($eventId);
        $this->orderManagementService->shouldReceive('deleteExistingOrders');

        $this->availabilityService->shouldReceive('getAvailableProductQuantities')
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    $this->createAvailabilityDTO(10, 100, 4),
                ]),
            ));

        $this->orderManagementService->shouldNotReceive('createNewOrder');

        $dto = $this->createMultiLineOrderDTO([
            [10, 100, 3, 1],
            [10, 100, 3, 2],
        ]);

        $this->expectException(ValidationException::class);
        $this->handler->handle($eventId, $dto);
    }

    private function createAvailabilityDTO(
        int $productId,
        int $priceId,
        int $available,
        string $productType = ProductType::TICKET->name,
    ): AvailableProductQuantitiesDTO {
        return AvailableProductQuantitiesDTO::fromArray([
            'product_id' => $productId,
            'price_id' => $priceId,
            'product_title' => 'Test',
            'product_type' => $productType,
            'price_label' => null,
            'quantity_available' => $available,
            'quantity_reserved' => 0,
            'initial_quantity_available' => 100,
        ]);
    }

    private function createMultiLineOrderDTO(array $lines): CreateOrderPublicDTO
    {
        return CreateOrderPublicDTO::fromArray([
            'is_user_authenticated' => false,
            'session_identifier' => 'test-session',
            'order_locale' => 'en',
            'products' => collect($lines)->map(fn (array $line) => ProductOrderDetailsDTO::fromArray([
                'product_id' => $line[0],
                'event_occurrence_id' => $line[3],
                'quantities' => collect([
                    OrderProductPriceDTO::fromArray([
                        'price_id' => $line[1],
                        'quantity' => $line[2],
                    ]),
                ]),
            ])),
        ]);
    }

    private function createOrderDTO(int $productId = 10, int $priceId = 100, int $quantity = 1): CreateOrderPublicDTO
    {
        return CreateOrderPublicDTO::fromArray([
            'is_user_authenticated' => false,
            'session_identifier' => 'test-session',
            'order_locale' => 'en',
            'products' => collect([
                ProductOrderDetailsDTO::fromArray([
                    'product_id' => $productId,
                    'event_occurrence_id' => 1,
                    'quantities' => collect([
                        OrderProductPriceDTO::fromArray([
                            'price_id' => $priceId,
                            'quantity' => $quantity,
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }

    private function setupEventMock(int $eventId): void
    {
        $eventSettings = Mockery::mock(EventSettingDomainObject::class);
        $eventSettings->shouldReceive('getOrderTimeoutInMinutes')->andReturn(15);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getId')->andReturn($eventId);
        $event->shouldReceive('getStatus')->andReturn(EventStatus::LIVE->name);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->with($eventId)->andReturn($event);
    }

    private function setupSuccessfulOrderCreation(
        int $eventId,
        int $productId = 10,
        int $priceId = 100,
        int $available = 10,
    ): void {
        $this->setupEventMock($eventId);

        $this->orderManagementService->shouldReceive('deleteExistingOrders');

        $this->availabilityService->shouldReceive('getAvailableProductQuantities')
            ->with($eventId, true, Mockery::any())
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    $this->createAvailabilityDTO($productId, $priceId, $available),
                ]),
            ));

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(1);

        $this->orderManagementService->shouldReceive('createNewOrder')->andReturn($order);

        $orderItems = collect([Mockery::mock(OrderItemDomainObject::class)]);
        $this->orderItemProcessingService->shouldReceive('process')->andReturn($orderItems);

        $this->orderManagementService->shouldReceive('updateOrderTotals')->andReturn($order);
    }
}
