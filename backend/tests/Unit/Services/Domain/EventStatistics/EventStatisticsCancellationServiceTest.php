<?php

namespace Tests\Unit\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDailyStatisticDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use HiEvents\Services\Infrastructure\Utlitiy\Retry\Retrier;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class EventStatisticsCancellationServiceTest extends TestCase
{
    private EventStatisticsCancellationService $service;
    private MockInterface|EventStatisticRepositoryInterface $eventStatisticsRepository;
    private MockInterface|EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository;
    private MockInterface|AttendeeRepositoryInterface $attendeeRepository;
    private MockInterface|OrderRepositoryInterface $orderRepository;
    private MockInterface|DatabaseManager $databaseManager;
    private MockInterface|LoggerInterface $logger;
    private MockInterface|Retrier $retrier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventStatisticsRepository = Mockery::mock(EventStatisticRepositoryInterface::class);
        $this->eventDailyStatisticRepository = Mockery::mock(EventDailyStatisticRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->retrier = Mockery::mock(Retrier::class);

        $this->service = new EventStatisticsCancellationService(
            $this->eventStatisticsRepository,
            $this->eventDailyStatisticRepository,
            $this->attendeeRepository,
            $this->orderRepository,
            $this->logger,
            $this->databaseManager,
            $this->retrier
        );
    }

    public function testDecrementForCancelledOrderSuccess(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';

        // Create mock order items
        $ticketOrderItem1 = Mockery::mock(OrderItemDomainObject::class);
        $ticketOrderItem1->shouldReceive('getQuantity')->andReturn(2);

        $ticketOrderItem2 = Mockery::mock(OrderItemDomainObject::class);
        $ticketOrderItem2->shouldReceive('getQuantity')->andReturn(1);

        $orderItems = new Collection([$ticketOrderItem1, $ticketOrderItem2]);
        $ticketOrderItems = new Collection([$ticketOrderItem1, $ticketOrderItem2]);

        // Create mock order
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getOrderItems')->andReturn($orderItems);
        $order->shouldReceive('getTicketOrderItems')->andReturn($ticketOrderItems);
        $order->shouldReceive('getStatisticsDecrementedAt')->andReturnNull();

        // Mock order repository to return order with relations
        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->with(OrderItemDomainObject::class)
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with($orderId)
            ->andReturn($order);

        // Mock aggregate event statistics
        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getId')->andReturn(1);
        $eventStatistics->shouldReceive('getAttendeesRegistered')->andReturn(10);
        $eventStatistics->shouldReceive('getProductsSold')->andReturn(15);
        $eventStatistics->shouldReceive('getOrdersCreated')->andReturn(5);
        $eventStatistics->shouldReceive('getOrdersCancelled')->andReturn(2);
        $eventStatistics->shouldReceive('getVersion')->andReturn(5);

        // Mock daily event statistics
        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getAttendeesRegistered')->andReturn(8);
        $eventDailyStatistic->shouldReceive('getProductsSold')->andReturn(12);
        $eventDailyStatistic->shouldReceive('getOrdersCreated')->andReturn(4);
        $eventDailyStatistic->shouldReceive('getOrdersCancelled')->andReturn(1);
        $eventDailyStatistic->shouldReceive('getVersion')->andReturn(3);

        // Mock attendee repository to return 2 active attendees (1 was already cancelled)
        $activeAttendee1 = Mockery::mock(AttendeeDomainObject::class);
        $activeAttendee2 = Mockery::mock(AttendeeDomainObject::class);
        $this->attendeeRepository
            ->shouldReceive('findWhereIn')
            ->with(
                'status',
                [AttendeeStatus::ACTIVE->name, AttendeeStatus::AWAITING_PAYMENT->name],
                ['order_id' => $orderId]
            )
            ->andReturn(new Collection([$activeAttendee1, $activeAttendee2]));

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

        // Expect updating aggregate statistics with decremented values
        // Note: We use full order quantities for products_sold since products don't get "uncancelled"
        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'attendees_registered' => 8,   // 10 - 2 (2 active attendees)
                    'products_sold' => 12,          // 15 - 3 (full order quantities)
                    'orders_created' => 4,          // 5 - 1
                    'orders_cancelled' => 3,        // 2 + 1
                    'version' => 6,                 // 5 + 1
                ],
                [
                    'id' => 1,
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

        // Expect updating daily statistics with decremented values
        // Note: We use full order quantities for products_sold since products don't get "uncancelled"
        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'attendees_registered' => 6,   // 8 - 2 (2 active attendees)
                    'products_sold' => 9,           // 12 - 3 (full order quantities)
                    'orders_created' => 3,          // 4 - 1
                    'orders_cancelled' => 2,        // 1 + 1
                    'version' => 4,                 // 3 + 1
                ],
                [
                    'event_id' => $eventId,
                    'date' => '2024-01-15',
                    'version' => 3,
                ]
            )
            ->andReturn(1);

        // Expect marking statistics as decremented
        $this->orderRepository
            ->shouldReceive('updateFromArray')
            ->with($orderId, Mockery::on(function ($data) {
                return array_key_exists('statistics_decremented_at', $data) && $data['statistics_decremented_at'] !== null;
            }))
            ->once();

        // Expect logging
        $this->logger->shouldReceive('info')->atLeast()->once();

        // Execute
        $this->service->decrementForCancelledOrder($order);

        $this->assertTrue(true);
    }

    public function testSkipsDecrementWhenAlreadyDecremented(): void
    {
        $orderId = 123;
        $eventId = 1;
        $decrementedAt = '2024-01-15 09:00:00';

        // Create mock order with statistics already decremented
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getStatisticsDecrementedAt')->andReturn($decrementedAt);

        // Mock order repository
        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->with(OrderItemDomainObject::class)
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with($orderId)
            ->andReturn($order);

        // Expect logging that statistics were already decremented
        $this->logger
            ->shouldReceive('info')
            ->with(
                'Statistics already decremented for cancelled order',
                [
                    'order_id' => $orderId,
                    'event_id' => $eventId,
                    'decremented_at' => $decrementedAt,
                ]
            )
            ->once();

        // Should not call any update methods
        $this->eventStatisticsRepository->shouldNotReceive('updateWhere');
        $this->eventDailyStatisticRepository->shouldNotReceive('updateWhere');
        $this->orderRepository->shouldNotReceive('updateFromArray');

        // Execute
        $this->service->decrementForCancelledOrder($order);

        $this->assertTrue(true);
    }

    public function testDecrementForCancelledAttendee(): void
    {
        $eventId = 1;
        $orderDate = '2024-01-15 10:30:00';
        $attendeeCount = 2;

        // Mock aggregate event statistics
        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getId')->andReturn(1);
        $eventStatistics->shouldReceive('getAttendeesRegistered')->andReturn(10);
        $eventStatistics->shouldReceive('getProductsSold')->andReturn(15);
        $eventStatistics->shouldReceive('getVersion')->andReturn(5);

        // Mock daily event statistics
        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getAttendeesRegistered')->andReturn(8);
        $eventDailyStatistic->shouldReceive('getProductsSold')->andReturn(12);
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

        // Expect updating aggregate statistics with decremented values
        // Note: products_sold should NOT be affected by individual attendee cancellations
        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'attendees_registered' => 8,   // 10 - 2
                    'version' => 6,                 // 5 + 1
                ],
                [
                    'id' => 1,
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

        // Expect updating daily statistics with decremented values
        // Note: products_sold should NOT be affected by individual attendee cancellations
        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'attendees_registered' => 6,   // 8 - 2
                    'version' => 4,                 // 3 + 1
                ],
                [
                    'event_id' => $eventId,
                    'date' => '2024-01-15',
                    'version' => 3,
                ]
            )
            ->andReturn(1);

        // Expect logging
        $this->logger->shouldReceive('info')->twice(); // One for aggregate, one for daily

        // Execute
        $this->service->decrementForCancelledAttendee($eventId, $orderDate, $attendeeCount);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
