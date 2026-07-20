<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Mail\Order\OrderCancelled;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Domain\Waitlist\RevertWaitlistOffersForCancelledOrderService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Mockery as m;
use Tests\TestCase;
use Throwable;

class OrderCancelServiceTest extends TestCase
{
    private Mailer $mailer;

    private AttendeeRepositoryInterface $attendeeRepository;

    private EventRepositoryInterface $eventRepository;

    private OrderRepositoryInterface $orderRepository;

    private DatabaseManager $databaseManager;

    private Connection $connection;

    private ProductQuantityUpdateService $productQuantityService;

    private OrderCancelService $service;

    private DomainEventDispatcherService $domainEventDispatcherService;

    private EventStatisticsCancellationService $eventStatisticsCancellationService;

    private RevertWaitlistOffersForCancelledOrderService $revertWaitlistOffersService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = m::mock(Mailer::class);
        $this->attendeeRepository = m::mock(AttendeeRepositoryInterface::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->orderRepository = m::mock(OrderRepositoryInterface::class);
        $this->databaseManager = m::mock(DatabaseManager::class);
        $this->connection = m::mock(Connection::class);
        $this->databaseManager->shouldReceive('connection')->andReturn($this->connection)->byDefault();
        $this->connection->shouldReceive('afterCommit')
            ->andReturnUsing(static fn (callable $callback) => $callback())
            ->byDefault();
        $this->productQuantityService = m::mock(ProductQuantityUpdateService::class);
        $this->domainEventDispatcherService = m::mock(DomainEventDispatcherService::class);
        $this->eventStatisticsCancellationService = m::mock(EventStatisticsCancellationService::class);
        $this->revertWaitlistOffersService = m::mock(RevertWaitlistOffersForCancelledOrderService::class);

        $this->service = new OrderCancelService(
            mailer: $this->mailer,
            attendeeRepository: $this->attendeeRepository,
            eventRepository: $this->eventRepository,
            orderRepository: $this->orderRepository,
            databaseManager: $this->databaseManager,
            productQuantityService: $this->productQuantityService,
            domainEventDispatcherService: $this->domainEventDispatcherService,
            eventStatisticsCancellationService: $this->eventStatisticsCancellationService,
            revertWaitlistOffersService: $this->revertWaitlistOffersService,
        );
    }

    public function test_cancel_order(): void
    {
        Event::fake();

        $order = $this->makeOrder(email: 'customer@example.com', completed: true);

        $attendee1 = $this->makeAttendee(productPriceId: 1, productId: 10);
        $attendee2 = $this->makeAttendee(productPriceId: 2, productId: 20);
        $attendees = new Collection([$attendee1, $attendee2]);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->twice()
            ->with(['order_id' => 1])
            ->andReturn($attendees);

        $this->attendeeRepository->shouldReceive('updateWhere')->once();

        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->once()->with(1, 1, 1);
        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->once()->with(2, 1, 1);

        $this->expectOrderItemsReload($order, [
            $this->makeOrderItem(ProductType::TICKET->name, productPriceId: 1, quantity: 1),
            $this->makeOrderItem(ProductType::GENERAL->name, productPriceId: 5, quantity: 3),
        ]);
        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->once()->with(5, 3);

        $this->orderRepository->shouldReceive('updateWhere')->once();

        $this->eventStatisticsCancellationService->shouldReceive('decrementForCancelledOrder')
            ->once()
            ->with($order);

        $this->revertWaitlistOffersService->shouldReceive('revertOffersForOrder')->once()->with(1)->andReturn([]);

        $this->expectCancellationEmail();

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->withArgs(function (OrderEvent $event) use ($order) {
                return $event->type === DomainEventType::ORDER_CANCELLED
                    && $event->orderId === $order->getId();
            })
            ->once();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $attendees->each(function ($attendee) {
            $attendee->shouldReceive('getStatus')->andReturn(AttendeeStatus::ACTIVE->name);
        });

        try {
            $this->service->cancelOrder($order);
        } catch (Throwable $e) {
            $this->fail('Failed to cancel order: '.$e->getMessage());
        }

        Event::assertDispatched(CapacityChangedEvent::class, 2);
        Event::assertDispatched(CapacityChangedEvent::class, function ($e) {
            return $e->eventId === 1 && $e->productId === 10;
        });
        Event::assertDispatched(CapacityChangedEvent::class, function ($e) {
            return $e->eventId === 1 && $e->productId === 20;
        });
    }

    public function test_cancel_order_awaiting_offline_payment(): void
    {
        Event::fake();

        $order = $this->makeOrder(email: 'customer@example.com', awaitingOfflinePayment: true);

        $attendee1 = $this->makeAttendee(productPriceId: 1, productId: 10);
        $attendee2 = $this->makeAttendee(productPriceId: 2, productId: 20);
        $attendees = new Collection([$attendee1, $attendee2]);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->twice()
            ->with(['order_id' => 1])
            ->andReturn($attendees);

        $this->attendeeRepository->shouldReceive('updateWhere')->once();

        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->twice();

        $this->expectOrderItemsReload($order, [
            $this->makeOrderItem(ProductType::TICKET->name, productPriceId: 1, quantity: 2),
        ]);

        $this->orderRepository->shouldReceive('updateWhere')->once();

        $this->eventStatisticsCancellationService->shouldReceive('decrementForCancelledOrder')
            ->once()
            ->with($order);

        $this->revertWaitlistOffersService->shouldReceive('revertOffersForOrder')->once()->with(1)->andReturn([]);

        $this->expectCancellationEmail();

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->withArgs(function (OrderEvent $event) use ($order) {
                return $event->type === DomainEventType::ORDER_CANCELLED
                    && $event->orderId === $order->getId();
            })
            ->once();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $attendees->each(function ($attendee) {
            $attendee->shouldReceive('getStatus')->andReturn(AttendeeStatus::AWAITING_PAYMENT->name);
        });

        try {
            $this->service->cancelOrder($order);
        } catch (Throwable $e) {
            $this->fail('Failed to cancel order: '.$e->getMessage());
        }

        $this->assertTrue(true, 'Order cancellation proceeded without throwing an exception.');
    }

    public function test_cancel_reserved_order_without_email_skips_email_and_reverts_waitlist_offers(): void
    {
        Event::fake();

        $order = $this->makeOrder(email: null);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->twice()
            ->with(['order_id' => 1])
            ->andReturn(new Collection);

        $this->attendeeRepository->shouldReceive('updateWhere')->once();

        $this->productQuantityService->shouldNotReceive('decreaseQuantitySold');
        $this->orderRepository->shouldNotReceive('loadRelation');

        $this->orderRepository->shouldReceive('updateWhere')->once();

        $this->eventStatisticsCancellationService->shouldReceive('decrementForCancelledOrder')
            ->once()
            ->with($order);

        $this->revertWaitlistOffersService->shouldReceive('revertOffersForOrder')->once()->with(1)->andReturn([]);

        $this->eventRepository->shouldNotReceive('loadRelation');
        $this->mailer->shouldNotReceive('to');
        $this->mailer->shouldNotReceive('send');

        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->service->cancelOrder($order);

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function test_cancel_order_awaiting_offline_payment_restores_non_ticket_quantities(): void
    {
        Event::fake();

        $order = $this->makeOrder(email: 'customer@example.com', awaitingOfflinePayment: true);

        $attendee = $this->makeAttendee(productPriceId: 1, productId: 10);
        $attendees = new Collection([$attendee]);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->twice()
            ->with(['order_id' => 1])
            ->andReturn($attendees);

        $this->attendeeRepository->shouldReceive('updateWhere')->once();

        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->once()->with(1, 1, 1);

        $this->expectOrderItemsReload($order, [
            $this->makeOrderItem(ProductType::TICKET->name, productPriceId: 1, quantity: 1),
            $this->makeOrderItem(ProductType::GENERAL->name, productPriceId: 5, quantity: 3),
        ]);
        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->once()->with(5, 3);

        $this->orderRepository->shouldReceive('updateWhere')->once();

        $this->eventStatisticsCancellationService->shouldReceive('decrementForCancelledOrder')
            ->once()
            ->with($order);

        $this->revertWaitlistOffersService->shouldReceive('revertOffersForOrder')->once()->with(1)->andReturn([]);

        $this->expectCancellationEmail();

        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $attendee->shouldReceive('getStatus')->andReturn(AttendeeStatus::AWAITING_PAYMENT->name);

        $this->service->cancelOrder($order);
    }

    public function test_waitlist_capacity_events_are_dispatched_after_commit(): void
    {
        Event::fake();

        $order = $this->makeOrder(email: null);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->twice()
            ->with(['order_id' => 1])
            ->andReturn(new Collection);

        $this->attendeeRepository->shouldReceive('updateWhere')->once();
        $this->orderRepository->shouldReceive('updateWhere')->once();

        $this->eventStatisticsCancellationService->shouldReceive('decrementForCancelledOrder')
            ->once()
            ->with($order);

        $waitlistCapacityEvent = new CapacityChangedEvent(
            eventId: 1,
            direction: CapacityChangeDirection::INCREASED,
            productId: 42,
            productPriceId: 9,
            eventOccurrenceId: 3,
        );

        $this->revertWaitlistOffersService
            ->shouldReceive('revertOffersForOrder')
            ->once()
            ->with(1)
            ->andReturn([$waitlistCapacityEvent]);

        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $capturedAfterCommitCallback = null;
        $this->connection->shouldReceive('afterCommit')
            ->once()
            ->andReturnUsing(static function (callable $callback) use (&$capturedAfterCommitCallback) {
                $capturedAfterCommitCallback = $callback;
            });

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            $result = $callback();

            Event::assertNotDispatched(CapacityChangedEvent::class);

            return $result;
        });

        $this->service->cancelOrder($order);

        Event::assertNotDispatched(CapacityChangedEvent::class);

        ($capturedAfterCommitCallback)();

        Event::assertDispatched(CapacityChangedEvent::class, function (CapacityChangedEvent $e) {
            return $e->eventId === 1
                && $e->direction === CapacityChangeDirection::INCREASED
                && $e->productId === 42
                && $e->productPriceId === 9
                && $e->eventOccurrenceId === 3;
        });
    }

    private function makeOrder(?string $email, bool $completed = false, bool $awaitingOfflinePayment = false): OrderDomainObject
    {
        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn(1);
        $order->shouldReceive('getId')->andReturn(1);
        $order->shouldReceive('getEmail')->andReturn($email);
        $order->shouldReceive('getLocale')->andReturn('en');
        $order->shouldReceive('isOrderCompleted')->andReturn($completed);
        $order->shouldReceive('isOrderAwaitingOfflinePayment')->andReturn($awaitingOfflinePayment);

        return $order;
    }

    private function makeAttendee(int $productPriceId, int $productId): AttendeeDomainObject
    {
        $attendee = m::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getProductPriceId')->andReturn($productPriceId);
        $attendee->shouldReceive('getProductId')->andReturn($productId);
        $attendee->shouldReceive('getEventOccurrenceId')->andReturn(1);

        return $attendee;
    }

    private function makeOrderItem(string $productType, int $productPriceId, int $quantity): OrderItemDomainObject
    {
        $orderItem = m::mock(OrderItemDomainObject::class);
        $orderItem->shouldReceive('getProductType')->andReturn($productType);
        $orderItem->shouldReceive('getProductPriceId')->andReturn($productPriceId);
        $orderItem->shouldReceive('getQuantity')->andReturn($quantity);

        return $orderItem;
    }

    private function expectOrderItemsReload(OrderDomainObject $order, array $orderItems): void
    {
        $orderWithItems = m::mock(OrderDomainObject::class);
        $orderWithItems->shouldReceive('getOrderItems')->andReturn(new Collection($orderItems));

        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->once()
            ->with(OrderItemDomainObject::class)
            ->andReturnSelf();
        $this->orderRepository
            ->shouldReceive('findById')
            ->once()
            ->with($order->getId())
            ->andReturn($orderWithItems);
    }

    private function expectCancellationEmail(): void
    {
        $event = new EventDomainObject;
        $event->setEventSettings(new EventSettingDomainObject);
        $event->setOrganizer(new OrganizerDomainObject);
        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->twice()
            ->andReturnSelf()
            ->getMock()
            ->shouldReceive('findById')->once()->andReturn($event);

        $this->mailer->shouldReceive('to')
            ->once()
            ->andReturnSelf();

        $this->mailer->shouldReceive('locale')
            ->once()
            ->andReturnSelf();

        $this->mailer->shouldReceive('send')->once()->withArgs(function ($mail) {
            return $mail instanceof OrderCancelled;
        });
    }
}
