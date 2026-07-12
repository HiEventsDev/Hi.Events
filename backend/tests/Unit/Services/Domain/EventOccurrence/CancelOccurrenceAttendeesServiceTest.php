<?php

namespace Tests\Unit\Services\Domain\EventOccurrence;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\EventOccurrence\CancelOccurrenceAttendeesService;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class CancelOccurrenceAttendeesServiceTest extends TestCase
{
    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private ProductQuantityUpdateService|MockInterface $productQuantityService;

    private DomainEventDispatcherService|MockInterface $domainEventDispatcherService;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private EventStatisticsCancellationService|MockInterface $statisticsCancellationService;

    private LoggerInterface|MockInterface $logger;

    private CancelOccurrenceAttendeesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->productQuantityService = Mockery::mock(ProductQuantityUpdateService::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->statisticsCancellationService = Mockery::mock(EventStatisticsCancellationService::class);
        $this->logger = Mockery::mock(LoggerInterface::class);

        // Default the logger to a permissive spy — only the failure path test
        // overrides it with a strict expectation.
        $this->logger->shouldReceive('error')->zeroOrMoreTimes()->byDefault();

        // Default the stats service / order repo to no-op (orderRepository
        // returns no orders → no decrement calls). Tests that exercise the
        // happy path provide explicit expectations that take precedence.
        $this->orderRepository
            ->shouldReceive('findWhereIn')
            ->zeroOrMoreTimes()
            ->andReturn(new Collection)
            ->byDefault();
        $this->statisticsCancellationService
            ->shouldReceive('decrementForCancelledAttendee')
            ->zeroOrMoreTimes()
            ->byDefault();

        $this->service = new CancelOccurrenceAttendeesService(
            $this->attendeeRepository,
            $this->productQuantityService,
            $this->domainEventDispatcherService,
            $this->orderRepository,
            $this->statisticsCancellationService,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cancels_active_attendees_and_decrements_quantities(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $attendeeA = $this->makeAttendee(id: 101, productId: 7, productPriceId: 70);
        $attendeeB = $this->makeAttendee(id: 102, productId: 7, productPriceId: 70);
        $attendeeC = $this->makeAttendee(id: 103, productId: 8, productPriceId: 80);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->with([
                AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
                [AttendeeDomainObjectAbstract::STATUS, 'in', [AttendeeStatus::ACTIVE->name, AttendeeStatus::AWAITING_PAYMENT->name]],
            ])
            ->andReturn(new Collection([$attendeeA, $attendeeB, $attendeeC]));

        // All three attendees are on a COMPLETED order → inventory was incremented → decrement.
        $this->orderRepository
            ->shouldReceive('findWhereIn')
            ->with('id', [1000])
            ->andReturn(new Collection([$this->makeOrder(id: 1000)]));

        $this->attendeeRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::CANCELLED->name],
                [
                    AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
                    [AttendeeDomainObjectAbstract::STATUS, 'in', [AttendeeStatus::ACTIVE->name, AttendeeStatus::AWAITING_PAYMENT->name]],
                ],
            );

        // Price 70 × 2 + price 80 × 1 = two grouped decrement calls.
        $this->productQuantityService
            ->shouldReceive('decreaseQuantitySold')->once()->with(70, 2, $occurrenceId);
        $this->productQuantityService
            ->shouldReceive('decreaseQuantitySold')->once()->with(80, 1, $occurrenceId);

        $this->domainEventDispatcherService
            ->shouldReceive('dispatch')
            ->times(3)
            ->with(Mockery::on(fn (AttendeeEvent $e) => $e->type === DomainEventType::ATTENDEE_CANCELLED
                && in_array($e->attendeeId, [101, 102, 103], true)));

        $this->service->cancelForOccurrence($eventId, $occurrenceId);

        Event::assertDispatched(
            CapacityChangedEvent::class,
            fn (CapacityChangedEvent $e) => $e->productId === 7 && $e->direction === CapacityChangeDirection::INCREASED,
        );
        Event::assertDispatched(
            CapacityChangedEvent::class,
            fn (CapacityChangedEvent $e) => $e->productId === 8 && $e->direction === CapacityChangeDirection::INCREASED,
        );
    }

    public function test_skips_everything_when_no_cancellable_attendees(): void
    {
        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->andReturn(new Collection);

        $this->attendeeRepository->shouldNotReceive('updateWhere');
        $this->productQuantityService->shouldNotReceive('decreaseQuantitySold');
        $this->domainEventDispatcherService->shouldNotReceive('dispatch');

        $this->service->cancelForOccurrence(1, 10);

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function test_also_cancels_awaiting_payment_attendees(): void
    {
        // Offline-payment attendees occupy a seat but have status AWAITING_PAYMENT.
        // They must be cancelled too when the occurrence is cancelled.
        $attendee = $this->makeAttendee(id: 201, productId: 5, productPriceId: 50);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->with(Mockery::on(function (array $where) {
                $statusClause = $where[0] ?? null;

                return is_array($statusClause)
                    && $statusClause[0] === AttendeeDomainObjectAbstract::STATUS
                    && $statusClause[1] === 'in'
                    && in_array(AttendeeStatus::AWAITING_PAYMENT->name, $statusClause[2], true);
            }))
            ->andReturn(new Collection([$attendee]));

        // Offline orders hold inventory but are not yet counted in statistics.
        $this->orderRepository
            ->shouldReceive('findWhereIn')
            ->with('id', [1000])
            ->andReturn(new Collection([$this->makeOrder(id: 1000, status: OrderStatus::AWAITING_OFFLINE_PAYMENT->name)]));

        $this->attendeeRepository->shouldReceive('updateWhere')->once();
        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->once();
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();
        $this->statisticsCancellationService->shouldNotReceive('decrementForCancelledAttendee');

        $this->service->cancelForOccurrence(1, 10);

        Event::assertDispatched(CapacityChangedEvent::class);
    }

    public function test_does_not_decrement_inventory_for_reserved_order_attendees(): void
    {
        // Reserved-order attendees never incremented inventory or statistics.
        $attendee = $this->makeAttendee(id: 501, productId: 9, productPriceId: 90, orderId: 7000);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->andReturn(new Collection([$attendee]));

        $this->orderRepository
            ->shouldReceive('findWhereIn')
            ->with('id', [7000])
            ->andReturn(new Collection([$this->makeOrder(id: 7000, status: OrderStatus::RESERVED->name)]));

        $this->attendeeRepository->shouldReceive('updateWhere')->once();
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $this->productQuantityService->shouldNotReceive('decreaseQuantitySold');
        $this->statisticsCancellationService->shouldNotReceive('decrementForCancelledAttendee');

        $this->service->cancelForOccurrence(1, 10);

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function test_decrements_attendee_statistics_grouped_by_source_order(): void
    {
        // Regression: bulk occurrence cancel previously cancelled attendees +
        // adjusted inventory but skipped the per-attendee statistics decrement
        // PartialEditAttendeeHandler runs. The later refund flow's order-level
        // decrement looked at currently-active attendees (zero) and decremented
        // attendees_registered by zero — leaving stats inflated. We now group
        // by source order and call the stats service once per order so daily
        // statistics for the right order date are touched.
        $eventId = 5;
        $occurrenceId = 50;

        // Two attendees on order 1000 (different products), one on order 1001.
        $a1 = $this->makeAttendee(id: 301, productId: 1, productPriceId: 11, orderId: 1000);
        $a2 = $this->makeAttendee(id: 302, productId: 2, productPriceId: 22, orderId: 1000);
        $a3 = $this->makeAttendee(id: 303, productId: 1, productPriceId: 11, orderId: 1001);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->andReturn(new Collection([$a1, $a2, $a3]));
        $this->attendeeRepository->shouldReceive('updateWhere')->once();
        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->zeroOrMoreTimes();
        $this->domainEventDispatcherService->shouldReceive('dispatch')->zeroOrMoreTimes();

        $order1000 = $this->makeOrder(id: 1000, createdAt: '2026-01-15 09:00:00');
        $order1001 = $this->makeOrder(id: 1001, createdAt: '2026-01-20 14:30:00');

        // Order ids are unique-collected before the lookup so order doesn't
        // matter — assert set membership.
        $this->orderRepository
            ->shouldReceive('findWhereIn')
            ->once()
            ->with('id', Mockery::on(function ($ids) {
                if (! is_array($ids)) {
                    return false;
                }
                $sorted = $ids;
                sort($sorted);

                return $sorted === [1000, 1001];
            }))
            ->andReturn(new Collection([$order1000, $order1001]));

        // Mockery's `with()` matches positionally — named-arg calls in the
        // service translate to positional under the hood, so assert that way.
        $this->statisticsCancellationService
            ->shouldReceive('decrementForCancelledAttendee')
            ->once()
            ->with($eventId, '2026-01-15 09:00:00', 2, $occurrenceId);
        $this->statisticsCancellationService
            ->shouldReceive('decrementForCancelledAttendee')
            ->once()
            ->with($eventId, '2026-01-20 14:30:00', 1, $occurrenceId);

        $this->service->cancelForOccurrence($eventId, $occurrenceId);

        // Mockery::close() in tearDown verifies the call expectations above.
        // Add a matching PHPUnit assertion so the test isn't marked risky.
        $this->assertTrue(true);
    }

    public function test_logs_and_continues_when_statistics_decrement_throws(): void
    {
        // Stats reconciliation failures must not roll back the attendee cancel
        // — attendees are already updated, inventory is already adjusted, and
        // a stats discrepancy is recoverable. The service swallows the error
        // and logs so on-call can spot drift.
        $attendee = $this->makeAttendee(id: 401, productId: 1, productPriceId: 11, orderId: 5000);
        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->andReturn(new Collection([$attendee]));
        $this->attendeeRepository->shouldReceive('updateWhere')->once();
        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->once();
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $order = $this->makeOrder(id: 5000);
        $this->orderRepository
            ->shouldReceive('findWhereIn')
            ->andReturn(new Collection([$order]));

        $this->statisticsCancellationService
            ->shouldReceive('decrementForCancelledAttendee')
            ->andThrow(new \RuntimeException('version mismatch'));

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with(
                'Failed to decrement attendee statistics during occurrence cancellation',
                Mockery::on(fn (array $ctx) => ($ctx['order_id'] ?? null) === 5000),
            );

        $this->service->cancelForOccurrence(1, 10);

        // Mockery verifies the logger + decrement expectations on close;
        // pair with a PHPUnit assertion so the test isn't marked risky.
        $this->assertTrue(true);
    }

    private function makeAttendee(int $id, int $productId, int $productPriceId, int $orderId = 1000): MockInterface
    {
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getId')->andReturn($id);
        $attendee->shouldReceive('getProductId')->andReturn($productId);
        $attendee->shouldReceive('getProductPriceId')->andReturn($productPriceId);
        $attendee->shouldReceive('getOrderId')->andReturn($orderId);

        return $attendee;
    }

    private function makeOrder(int $id, string $createdAt = '2026-01-01 12:00:00', ?string $status = null): MockInterface
    {
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn($id);
        $order->shouldReceive('getCreatedAt')->andReturn($createdAt);
        $order->shouldReceive('getStatus')->andReturn($status ?? OrderStatus::COMPLETED->name);

        return $order;
    }
}
