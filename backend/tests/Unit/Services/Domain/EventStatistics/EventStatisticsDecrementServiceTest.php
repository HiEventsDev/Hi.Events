<?php

namespace Tests\Unit\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\EventDailyStatisticDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsDecrementService;
use HiEvents\Services\Infrastructure\Utlitiy\Retry\Retrier;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class EventStatisticsDecrementServiceTest extends TestCase
{
    private EventStatisticsDecrementService $service;
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
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->retrier = Mockery::mock(Retrier::class);

        $this->service = new EventStatisticsDecrementService(
            $this->eventStatisticsRepository,
            $this->eventDailyStatisticRepository,
            $this->attendeeRepository,
            $this->orderRepository,
            $this->logger,
            $this->databaseManager,
            $this->retrier
        );
    }

    public function testDecrementStatisticsForCancelledOrder(): void
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
        $eventStatistics->shouldReceive('getVersion')->andReturn(5);

        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        // Mock daily event statistics
        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getId')->andReturn(1);
        $eventDailyStatistic->shouldReceive('getAttendeesRegistered')->andReturn(8);
        $eventDailyStatistic->shouldReceive('getProductsSold')->andReturn(12);
        $eventDailyStatistic->shouldReceive('getOrdersCreated')->andReturn(3);
        $eventDailyStatistic->shouldReceive('getVersion')->andReturn(2);

        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturn($eventDailyStatistic);

        // Mock attendee repository
        $activeAttendees = collect([1, 2, 3]);
        $this->attendeeRepository
            ->shouldReceive('findWhereIn')
            ->with('status', [AttendeeStatus::ACTIVE->name, AttendeeStatus::AWAITING_PAYMENT->name], [
                'order_id' => $orderId,
            ])
            ->andReturn($activeAttendees);

        // Mock aggregate statistics update
        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->withArgs(function ($attributes, $where) {
                return $attributes === [
                        'attendees_registered' => 7,  // 10 - 3
                        'products_sold' => 12,        // 15 - 3
                        'orders_created' => 4,        // 5 - 1
                        'version' => 6,               // 5 + 1
                    ] && $where === ['id' => 1, 'version' => 5];
            })
            ->once()
            ->andReturn(1);

        // Mock daily statistics update
        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->withArgs(function ($attributes, $where) {
                return $attributes === [
                        'attendees_registered' => 5,  // 8 - 3
                        'products_sold' => 9,         // 12 - 3
                        'orders_created' => 2,        // 3 - 1
                        'version' => 3,               // 2 + 1
                    ] && $where === [
                        'event_id' => 1,
                        'date' => '2024-01-15',
                        'version' => 2,
                    ];
            })
            ->once()
            ->andReturn(1);

        // Mock logger
        $this->logger->shouldReceive('info')->twice();

        // Mock database transaction
        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock retrier
        $this->retrier
            ->shouldReceive('retry')
            ->andReturnUsing(function ($callableAction, $onFailure, $retryOn) {
                $callableAction(1);
            });

        $this->service->decrementStatisticsForCancelledOrder($order);
    }

    public function testDecrementStatisticsDoesNotGoBelowZero(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';

        // Create order items with large quantities
        $orderItem = Mockery::mock(OrderItemDomainObject::class);
        $orderItem->shouldReceive('getQuantity')->andReturn(20);

        $orderItems = new Collection([$orderItem]);

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getOrderItems')->andReturn($orderItems);
        $order->shouldReceive('getTicketOrderItems')->andReturn($orderItems);

        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->with(OrderItemDomainObject::class)
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with($orderId)
            ->andReturn($order);

        // Mock aggregate statistics with small values
        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getId')->andReturn(1);
        $eventStatistics->shouldReceive('getAttendeesRegistered')->andReturn(2);
        $eventStatistics->shouldReceive('getProductsSold')->andReturn(1);
        $eventStatistics->shouldReceive('getOrdersCreated')->andReturn(1);
        $eventStatistics->shouldReceive('getVersion')->andReturn(2);

        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        // Mock daily statistics with small values
        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getId')->andReturn(1);
        $eventDailyStatistic->shouldReceive('getAttendeesRegistered')->andReturn(1);
        $eventDailyStatistic->shouldReceive('getProductsSold')->andReturn(1);
        $eventDailyStatistic->shouldReceive('getOrdersCreated')->andReturn(1);
        $eventDailyStatistic->shouldReceive('getVersion')->andReturn(1);

        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturn($eventDailyStatistic);

        // Mock large attendee count
        $activeAttendees = collect(range(1, 10));
        $this->attendeeRepository
            ->shouldReceive('findWhereIn')
            ->andReturn($activeAttendees);

        // Verify values don't go below zero in aggregate update
        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->withArgs(function ($attributes, $where) {
                return $attributes === [
                        'attendees_registered' => 0,  // max(0, 2 - 20) = 0
                        'products_sold' => 0,         // max(0, 1 - 20) = 0
                        'orders_created' => 0,        // max(0, 1 - 1) = 0
                        'version' => 3,               // 2 + 1
                    ] && $where === ['id' => 1, 'version' => 2];
            })
            ->once()
            ->andReturn(1);

        // Verify values don't go below zero in daily update
        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->withArgs(function ($attributes, $where) {
                return $attributes === [
                        'attendees_registered' => 0,  // max(0, 1 - 20) = 0
                        'products_sold' => 0,         // max(0, 1 - 20) = 0
                        'orders_created' => 0,        // max(0, 1 - 1) = 0
                        'version' => 2,               // 1 + 1
                    ] && $where === [
                        'event_id' => 1,
                        'date' => '2024-01-15',
                        'version' => 1,
                    ];
            })
            ->once()
            ->andReturn(1);

        $this->logger->shouldReceive('info')->twice();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->retrier
            ->shouldReceive('retry')
            ->andReturnUsing(function ($callableAction, $onFailure, $retryOn) {
                $callableAction(1);
            });

        $this->service->decrementStatisticsForCancelledOrder($order);
    }

    public function testDecrementStatisticsWhenNoDailyStatisticsExist(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';

        $orderItem = Mockery::mock(OrderItemDomainObject::class);
        $orderItem->shouldReceive('getQuantity')->andReturn(2);

        $orderItems = new Collection([$orderItem]);

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getOrderItems')->andReturn($orderItems);
        $order->shouldReceive('getTicketOrderItems')->andReturn($orderItems);

        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->with(OrderItemDomainObject::class)
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with($orderId)
            ->andReturn($order);

        // Mock aggregate statistics exist
        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getId')->andReturn(1);
        $eventStatistics->shouldReceive('getAttendeesRegistered')->andReturn(5);
        $eventStatistics->shouldReceive('getProductsSold')->andReturn(5);
        $eventStatistics->shouldReceive('getOrdersCreated')->andReturn(2);
        $eventStatistics->shouldReceive('getVersion')->andReturn(1);

        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        // Mock daily statistics don't exist
        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturn(null);

        $activeAttendees = collect([1, 2]);
        $this->attendeeRepository
            ->shouldReceive('findWhereIn')
            ->andReturn($activeAttendees);

        // Aggregate stats should still update
        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->andReturn(1);

        // Daily stats should not be updated
        $this->eventDailyStatisticRepository
            ->shouldNotReceive('updateWhere');

        // Logger should warn about missing daily stats
        $this->logger->shouldReceive('warning')
            ->withArgs(function ($message, $context) use ($eventId) {
                return str_contains($message, 'Event daily statistics not found') &&
                    $context['event_id'] === $eventId &&
                    $context['date'] === '2024-01-15';
            })
            ->once();

        $this->logger->shouldReceive('info')->once();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->retrier
            ->shouldReceive('retry')
            ->andReturnUsing(function ($callableAction, $onFailure, $retryOn) {
                $callableAction(1);
            });

        $this->service->decrementStatisticsForCancelledOrder($order);
    }
}
