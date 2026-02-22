<?php

namespace Tests\Unit\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\EventDomainObject;
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
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateOrderHandlerTest extends TestCase
{
    private EventRepositoryInterface|MockInterface $eventRepository;
    private PromoCodeRepositoryInterface|MockInterface $promoCodeRepository;
    private AffiliateRepositoryInterface|MockInterface $affiliateRepository;
    private OrderManagementService|MockInterface $orderManagementService;
    private OrderItemProcessingService|MockInterface $orderItemProcessingService;
    private AvailableProductQuantitiesFetchService|MockInterface $availabilityService;
    private DatabaseManager|MockInterface $databaseManager;
    private CreateOrderHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $this->affiliateRepository = Mockery::mock(AffiliateRepositoryInterface::class);
        $this->orderManagementService = Mockery::mock(OrderManagementService::class);
        $this->orderItemProcessingService = Mockery::mock(OrderItemProcessingService::class);
        $this->availabilityService = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new CreateOrderHandler(
            $this->eventRepository,
            $this->promoCodeRepository,
            $this->affiliateRepository,
            $this->orderManagementService,
            $this->orderItemProcessingService,
            $this->availabilityService,
            $this->databaseManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAcquiresAdvisoryLockBeforeCreatingOrder(): void
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

    public function testThrowsWhenProductQuantityExceedsAvailability(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);

        $this->setupEventMock($eventId);
        $this->orderManagementService->shouldReceive('deleteExistingOrders');

        $this->availabilityService->shouldReceive('getAvailableProductQuantities')
            ->with($eventId, true)
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    AvailableProductQuantitiesDTO::fromArray([
                        'product_id' => 10,
                        'price_id' => 100,
                        'product_title' => 'Test',
                        'price_label' => null,
                        'quantity_available' => 2,
                        'quantity_reserved' => 0,
                        'initial_quantity_available' => 10,
                    ]),
                ]),
            ));

        $dto = $this->createOrderDTO(quantity: 5);

        $this->expectException(ValidationException::class);
        $this->handler->handle($eventId, $dto);
    }

    public function testPassesWhenQuantityIsWithinAvailability(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);
        $this->setupSuccessfulOrderCreation($eventId, productId: 10, priceId: 100, available: 5);

        $dto = $this->createOrderDTO(quantity: 2);

        $result = $this->handler->handle($eventId, $dto);
        $this->assertInstanceOf(OrderDomainObject::class, $result);
    }

    public function testSkipsZeroQuantityProducts(): void
    {
        $eventId = 1;

        $this->databaseManager->shouldReceive('statement')->andReturn(true);
        $this->setupSuccessfulOrderCreation($eventId, available: 0);

        $dto = $this->createOrderDTO(quantity: 0);

        $result = $this->handler->handle($eventId, $dto);
        $this->assertInstanceOf(OrderDomainObject::class, $result);
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
    ): void
    {
        $this->setupEventMock($eventId);

        $this->orderManagementService->shouldReceive('deleteExistingOrders');

        $this->availabilityService->shouldReceive('getAvailableProductQuantities')
            ->with($eventId, true)
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    AvailableProductQuantitiesDTO::fromArray([
                        'product_id' => $productId,
                        'price_id' => $priceId,
                        'product_title' => 'Test Product',
                        'price_label' => null,
                        'quantity_available' => $available,
                        'quantity_reserved' => 0,
                        'initial_quantity_available' => 100,
                    ]),
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
