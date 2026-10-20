<?php

namespace Tests\Unit\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDailyStatisticDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
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

    private MockInterface|PromoCodeRepositoryInterface $promoCodeRepository;

    private MockInterface|ProductRepositoryInterface $productRepository;

    private MockInterface|AffiliateRepositoryInterface $affiliateRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventStatisticsRepository = Mockery::mock(EventStatisticRepositoryInterface::class);
        $this->eventDailyStatisticRepository = Mockery::mock(EventDailyStatisticRepositoryInterface::class);
        $eventOccurrenceStatisticRepository = Mockery::mock(EventOccurrenceStatisticRepositoryInterface::class);
        $eventOccurrenceStatisticRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $eventOccurrenceDailyStatisticRepository = Mockery::mock(EventOccurrenceDailyStatisticRepositoryInterface::class);
        $eventOccurrenceDailyStatisticRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->retrier = Mockery::mock(Retrier::class);
        $this->promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->affiliateRepository = Mockery::mock(AffiliateRepositoryInterface::class);

        $this->service = new EventStatisticsCancellationService(
            $this->eventStatisticsRepository,
            $this->eventDailyStatisticRepository,
            $eventOccurrenceStatisticRepository,
            $eventOccurrenceDailyStatisticRepository,
            $this->attendeeRepository,
            $this->orderRepository,
            $this->logger,
            $this->databaseManager,
            $this->retrier,
            $this->promoCodeRepository,
            $this->productRepository,
            $this->affiliateRepository,
        );
    }

    public function test_decrement_for_cancelled_order_success(): void
    {
        $eventId = 1;
        $orderId = 123;
        $orderDate = '2024-01-15 10:30:00';

        $ticketOrderItem1 = Mockery::mock(OrderItemDomainObject::class);
        $ticketOrderItem1->shouldReceive('getQuantity')->andReturn(2);
        $ticketOrderItem1->shouldReceive('getEventOccurrenceId')->andReturnNull();
        $ticketOrderItem1->shouldReceive('getProductId')->andReturn(7);
        $ticketOrderItem1->shouldReceive('getTotalBeforeAdditions')->andReturn(20.0);

        $ticketOrderItem2 = Mockery::mock(OrderItemDomainObject::class);
        $ticketOrderItem2->shouldReceive('getQuantity')->andReturn(1);
        $ticketOrderItem2->shouldReceive('getEventOccurrenceId')->andReturnNull();
        $ticketOrderItem2->shouldReceive('getProductId')->andReturn(8);
        $ticketOrderItem2->shouldReceive('getTotalBeforeAdditions')->andReturn(10.0);

        $orderItems = new Collection([$ticketOrderItem1, $ticketOrderItem2]);
        $ticketOrderItems = new Collection([$ticketOrderItem1, $ticketOrderItem2]);

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getCreatedAt')->andReturn($orderDate);
        $order->shouldReceive('getOrderItems')->andReturn($orderItems);
        $order->shouldReceive('getTicketOrderItems')->andReturn($ticketOrderItems);
        $order->shouldReceive('getStatisticsDecrementedAt')->andReturnNull();
        $order->shouldReceive('isOrderCompleted')->andReturnTrue();
        $order->shouldReceive('getPromoCodeId')->andReturn(55);
        $order->shouldReceive('getAffiliateId')->andReturn(99);
        $order->shouldReceive('getTotalGross')->andReturn(100.0);

        $this->promoCodeRepository
            ->shouldReceive('decrementEach')
            ->once()
            ->with(['id' => 55], ['order_usage_count' => 1, 'attendee_usage_count' => 3]);
        $this->productRepository->shouldReceive('decrement')->twice();
        $this->affiliateRepository->shouldReceive('decrementSales')->once()->with(99, 100.0);

        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->with(OrderItemDomainObject::class)
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with($orderId)
            ->andReturn($order);

        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getId')->andReturn(1);
        $eventStatistics->shouldReceive('getAttendeesRegistered')->andReturn(10);
        $eventStatistics->shouldReceive('getProductsSold')->andReturn(15);
        $eventStatistics->shouldReceive('getOrdersCreated')->andReturn(5);
        $eventStatistics->shouldReceive('getOrdersCancelled')->andReturn(2);
        $eventStatistics->shouldReceive('getVersion')->andReturn(5);

        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getAttendeesRegistered')->andReturn(8);
        $eventDailyStatistic->shouldReceive('getProductsSold')->andReturn(12);
        $eventDailyStatistic->shouldReceive('getOrdersCreated')->andReturn(4);
        $eventDailyStatistic->shouldReceive('getOrdersCancelled')->andReturn(1);
        $eventDailyStatistic->shouldReceive('getVersion')->andReturn(3);

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

        $this->retrier
            ->shouldReceive('retry')
            ->andReturnUsing(function ($callableAction) {
                return $callableAction(1);
            });

        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'attendees_registered' => 8,
                    'products_sold' => 12,
                    'orders_created' => 4,
                    'orders_cancelled' => 3,
                    'version' => 6,
                ],
                [
                    'id' => 1,
                    'version' => 5,
                ]
            )
            ->andReturn(1);

        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturn($eventDailyStatistic);

        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'attendees_registered' => 6,
                    'products_sold' => 9,
                    'orders_created' => 3,
                    'orders_cancelled' => 2,
                    'version' => 4,
                ],
                [
                    'event_id' => $eventId,
                    'date' => '2024-01-15',
                    'version' => 3,
                ]
            )
            ->andReturn(1);

        $this->orderRepository
            ->shouldReceive('updateFromArray')
            ->with($orderId, Mockery::on(function ($data) {
                return array_key_exists('statistics_decremented_at', $data) && $data['statistics_decremented_at'] !== null;
            }))
            ->once();

        $this->logger->shouldReceive('info')->atLeast()->once();

        $this->service->decrementForCancelledOrder($order);

        $this->assertTrue(true);
    }

    public function test_never_completed_order_touches_no_statistics_but_is_marked_decremented(): void
    {
        $eventId = 1;
        $orderId = 456;

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getStatisticsDecrementedAt')->andReturnNull();
        $order->shouldReceive('isOrderCompleted')->andReturnFalse();

        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('findById')->with($orderId)->andReturn($order);

        $this->eventStatisticsRepository->shouldNotReceive('findFirstWhere');
        $this->eventStatisticsRepository->shouldNotReceive('updateWhere');
        $this->eventDailyStatisticRepository->shouldNotReceive('findFirstWhere');
        $this->eventDailyStatisticRepository->shouldNotReceive('updateWhere');
        $this->attendeeRepository->shouldNotReceive('findWhereIn');
        $this->promoCodeRepository->shouldNotReceive('decrementEach');
        $this->productRepository->shouldNotReceive('decrement');
        $this->affiliateRepository->shouldNotReceive('decrementSales');
        $this->retrier->shouldNotReceive('retry');

        $this->orderRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($orderId, Mockery::on(function ($data) {
                return array_key_exists('statistics_decremented_at', $data) && $data['statistics_decremented_at'] !== null;
            }));
        $this->logger->shouldReceive('info')->atLeast()->once();

        $this->service->decrementForCancelledOrder($order);

        $this->assertTrue(true);
    }

    public function test_skips_decrement_when_already_decremented(): void
    {
        $orderId = 123;
        $eventId = 1;
        $decrementedAt = '2024-01-15 09:00:00';

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('getStatisticsDecrementedAt')->andReturn($decrementedAt);

        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->with(OrderItemDomainObject::class)
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with($orderId)
            ->andReturn($order);

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

        $this->eventStatisticsRepository->shouldNotReceive('updateWhere');
        $this->eventDailyStatisticRepository->shouldNotReceive('updateWhere');
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->service->decrementForCancelledOrder($order);

        $this->assertTrue(true);
    }

    public function test_decrement_for_cancelled_attendee(): void
    {
        $eventId = 1;
        $orderDate = '2024-01-15 10:30:00';
        $attendeeCount = 2;

        $eventStatistics = Mockery::mock(EventStatisticDomainObject::class);
        $eventStatistics->shouldReceive('getId')->andReturn(1);
        $eventStatistics->shouldReceive('getAttendeesRegistered')->andReturn(10);
        $eventStatistics->shouldReceive('getProductsSold')->andReturn(15);
        $eventStatistics->shouldReceive('getVersion')->andReturn(5);

        $eventDailyStatistic = Mockery::mock(EventDailyStatisticDomainObject::class);
        $eventDailyStatistic->shouldReceive('getAttendeesRegistered')->andReturn(8);
        $eventDailyStatistic->shouldReceive('getProductsSold')->andReturn(12);
        $eventDailyStatistic->shouldReceive('getVersion')->andReturn(3);

        $this->retrier
            ->shouldReceive('retry')
            ->andReturnUsing(function ($callableAction) {
                return $callableAction(1);
            });

        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->eventStatisticsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => $eventId])
            ->andReturn($eventStatistics);

        $this->eventStatisticsRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'attendees_registered' => 8,
                    'version' => 6,
                ],
                [
                    'id' => 1,
                    'version' => 5,
                ]
            )
            ->andReturn(1);

        $this->eventDailyStatisticRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                'event_id' => $eventId,
                'date' => '2024-01-15',
            ])
            ->andReturn($eventDailyStatistic);

        $this->eventDailyStatisticRepository
            ->shouldReceive('updateWhere')
            ->with(
                [
                    'attendees_registered' => 6,
                    'version' => 4,
                ],
                [
                    'event_id' => $eventId,
                    'date' => '2024-01-15',
                    'version' => 3,
                ]
            )
            ->andReturn(1);

        $this->logger->shouldReceive('info')->twice();

        $this->service->decrementForCancelledAttendee($eventId, $orderDate, $attendeeCount);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
