<?php

namespace Tests\Unit\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\EventDailyStatisticDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsIncrementService;
use HiEvents\Services\Infrastructure\Utlitiy\Retry\Retrier;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class EventStatisticsIncrementServiceTest extends TestCase
{
    private EventStatisticsIncrementService $service;
    private MockInterface|PromoCodeRepositoryInterface $promoCodeRepository;
    private MockInterface|ProductRepositoryInterface $productRepository;
    private MockInterface|EventStatisticRepositoryInterface $eventStatisticsRepository;
    private MockInterface|EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository;
    private MockInterface|DatabaseManager $databaseManager;
    private MockInterface|OrderRepositoryInterface $orderRepository;
    private MockInterface|LoggerInterface $logger;
    private MockInterface|Retrier $retrier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->eventStatisticsRepository = Mockery::mock(EventStatisticRepositoryInterface::class);
        $this->eventDailyStatisticRepository = Mockery::mock(EventDailyStatisticRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->retrier = Mockery::mock(Retrier::class);

        $this->service = new EventStatisticsIncrementService(
            $this->promoCodeRepository,
            $this->productRepository,
            $this->eventStatisticsRepository,
            $this->eventDailyStatisticRepository,
            $this->databaseManager,
            $this->orderRepository,
            $this->logger,
            $this->retrier
        );
    }

    public function testIncrementForOrderWithExistingStatistics(): void
    {
        $eventId = 1;
        $orderId = 123;
        $promoCodeId = 456;
        $orderDate = '2024-01-15 10:30:00';

        // Create mock order items
        $ticketOrderItem1 = Mockery::mock(OrderItemDomainObject::class);
        $ticketOrderItem1->shouldReceive('getQuantity')->andReturn(2);
        $ticketOrderItem1->shouldReceive('getProductId')->andReturn(1);
        $ticketOrderItem1->shouldReceive('getTotalBeforeAdditions')->andReturn(100.00);

        $ticketOrderItem2 = Mockery::mock(OrderItemDomainObject::class);
        $ticketOrderItem2->shouldReceive('getQuantity')->andReturn(1);
        $ticketOrderItem2->shouldReceive('getProductId')->andReturn(2);
        $ticketOrderItem2->shouldReceive('getTotalBeforeAdditions')->andReturn(50.00);

        $orderItems = new Collection([$ticketOrderItem1, $ticketOrderItem2]);
        $ticketOrderItems = new Collection([$ticketOrderItem1, $ticketOrderItem2]);

        // Create mock order
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getOrderItems')->andReturn($orderItems);
        $order->shouldReceive('getTicketOrderItems')->andReturn($ticketOrderItems);
        $order->shouldReceive('getPromoCodeId')->andReturn($promoCodeId);
        $order->shouldReceive('getTotalGross')->andReturn(150.00);
        $order->shouldReceive('getTotalBeforeAdditions')->andReturn(140.00);
        $order->shouldReceive('getTotalTax')->andReturn(8.00);
        $order->shouldReceive('getTotalFee')->andReturn(2.00);

        // Mock order repository to return order with relations
        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->with(OrderItemDomainObject::class)
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with($orderId)
            ->andReturn($order);

        // Mock existing aggregate event statistics
        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getAttendeesRegistered')->andReturn(10);
        $eventStatistics->shouldReceive('getProductsSold')->andReturn(15);
        $eventStatistics->shouldReceive('getOrdersCreated')->andReturn(5);
        $eventStatistics->shouldReceive('getSalesTotalGross')->andReturn(500.00);
        $eventStatistics->shouldReceive('getSalesTotalBeforeAdditions')->andReturn(480.00);
        $eventStatistics->shouldReceive('getTotalTax')->andReturn(15.00);
        $eventStatistics->shouldReceive('getTotalFee')->andReturn(5.00);
        $eventStatistics->shouldReceive('getVersion')->andReturn(5);

        // Mock existing daily event statistics
        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getAttendeesRegistered')->andReturn(8);
        $eventDailyStatistic->shouldReceive('getProductsSold')->andReturn(12);
        $eventDailyStatistic->shouldReceive('getOrdersCreated')->andReturn(4);
        $eventDailyStatistic->shouldReceive('getSalesTotalGross')->andReturn(400.00);
        $eventDailyStatistic->shouldReceive('getSalesTotalBeforeAdditions')->andReturn(380.00);
        $eventDailyStatistic->shouldReceive('getTotalTax')->andReturn(12.00);
        $eventDailyStatistic->shouldReceive('getTotalFee')->andReturn(3.00);
        $eventDailyStatistic->shouldReceive('getVersion')->andReturn(3);

        // Set up retrier to execute the action immediately
        $this->retrier
            ->shouldReceive('retry')
            ->andReturnUsing(function ($callableAction) {
                return $callableAction(1);
            });

        // Set up database transaction
        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Expect finding aggregate statistics
        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        // Expect updating aggregate statistics
        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'products_sold' => 18,             // 15 + 3
                    'attendees_registered' => 13,       // 10 + 3
                    'sales_total_gross' => 650.00,      // 500 + 150
                    'sales_total_before_additions' => 620.00, // 480 + 140
                    'total_tax' => 23.00,               // 15 + 8
                    'total_fee' => 7.00,                // 5 + 2
                    'orders_created' => 6,              // 5 + 1
                    'version' => 6,                     // 5 + 1
                ],
                [
                    'event_id' => $eventId,
                    'version' => 5,
                ]
            )
            ->andReturn(1);

        // Expect finding daily statistics
        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturn($eventDailyStatistic);

        // Expect updating daily statistics
        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'products_sold' => 15,              // 12 + 3
                    'attendees_registered' => 11,       // 8 + 3
                    'sales_total_gross' => 550.00,      // 400 + 150
                    'sales_total_before_additions' => 520.00, // 380 + 140
                    'total_tax' => 20.00,               // 12 + 8
                    'total_fee' => 5.00,                // 3 + 2
                    'orders_created' => 5,              // 4 + 1
                    'version' => 4,                     // 3 + 1
                ],
                [
                    'event_id' => $eventId,
                    'date' => '2024-01-15',
                    'version' => 3,
                ]
            )
            ->andReturn(1);

        // Expect incrementing promo code usage
        $this->promoCodeRepository
            ->shouldReceive('increment')
            ->with($promoCodeId, PromoCodeDomainObjectAbstract::ORDER_USAGE_COUNT)
            ->once();

        $this->promoCodeRepository
            ->shouldReceive('increment')
            ->with($promoCodeId, PromoCodeDomainObjectAbstract::ATTENDEE_USAGE_COUNT, 3)
            ->once();

        // Expect incrementing product statistics
        $this->productRepository
            ->shouldReceive('increment')
            ->with(1, ProductDomainObjectAbstract::SALES_VOLUME, 100.00)
            ->once();

        $this->productRepository
            ->shouldReceive('increment')
            ->with(2, ProductDomainObjectAbstract::SALES_VOLUME, 50.00)
            ->once();

        // Expect logging
        $this->logger->shouldReceive('info')->atLeast()->once();

        // Execute
        $this->service->incrementForOrder($order);


        $this->assertTrue(true);
    }

    public function testIncrementForOrderCreatesNewStatistics(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';

        // Create mock order item
        $orderItem = Mockery::mock(OrderItemDomainObject::class);
        $orderItem->shouldReceive('getQuantity')->andReturn(2);
        $orderItem->shouldReceive('getProductId')->andReturn(1);
        $orderItem->shouldReceive('getTotalBeforeAdditions')->andReturn(100.00);

        $orderItems = new Collection([$orderItem]);
        $ticketOrderItems = new Collection([$orderItem]);

        // Create mock order
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getOrderItems')->andReturn($orderItems);
        $order->shouldReceive('getTicketOrderItems')->andReturn($ticketOrderItems);
        $order->shouldReceive('getPromoCodeId')->andReturnNull();
        $order->shouldReceive('getTotalGross')->andReturn(100.00);
        $order->shouldReceive('getTotalBeforeAdditions')->andReturn(95.00);
        $order->shouldReceive('getTotalTax')->andReturn(4.00);
        $order->shouldReceive('getTotalFee')->andReturn(1.00);

        // Mock order repository
        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->with(OrderItemDomainObject::class)
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with($orderId)
            ->andReturn($order);

        // Set up retrier
        $this->retrier
            ->shouldReceive('retry')
            ->andReturnUsing(function ($callableAction) {
                return $callableAction(1);
            });

        // Set up database transaction
        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Expect aggregate statistics not found, so create new
        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturnNull();

        $this->eventStatisticsRepository
            ->shouldReceive('create')
            ->with([
                'event_id' => $eventId,
                'products_sold' => 2,
                'attendees_registered' => 2,
                'sales_total_gross' => 100.00,
                'sales_total_before_additions' => 95.00,
                'total_tax' => 4.00,
                'total_fee' => 1.00,
                'orders_created' => 1,
                'orders_cancelled' => 0,
            ])
            ->once();

        // Expect daily statistics not found, so create new
        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturnNull();

        $this->eventDailyStatisticRepository
            ->shouldReceive('create')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
                'products_sold' => 2,
                'attendees_registered' => 2,
                'sales_total_gross' => 100.00,
                'sales_total_before_additions' => 95.00,
                'total_tax' => 4.00,
                'total_fee' => 1.00,
                'orders_created' => 1,
                'orders_cancelled' => 0,
            ])
            ->once();

        // Expect incrementing product statistics
        $this->productRepository
            ->shouldReceive('increment')
            ->with(1, ProductDomainObjectAbstract::SALES_VOLUME, 100.00)
            ->once();

        // Expect logging
        $this->logger->shouldReceive('info')->atLeast()->once();

        // Execute
        $this->service->incrementForOrder($order);


        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
