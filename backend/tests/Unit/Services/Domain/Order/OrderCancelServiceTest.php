<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Mail\Order\OrderCancelled;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
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
    private ProductQuantityUpdateService $productQuantityService;
    private OrderCancelService $service;
    private DomainEventDispatcherService $domainEventDispatcherService;
    private EventStatisticsCancellationService $eventStatisticsCancellationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = m::mock(Mailer::class);
        $this->attendeeRepository = m::mock(AttendeeRepositoryInterface::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->orderRepository = m::mock(OrderRepositoryInterface::class);
        $this->databaseManager = m::mock(DatabaseManager::class);
        $this->productQuantityService = m::mock(ProductQuantityUpdateService::class);
        $this->domainEventDispatcherService = m::mock(DomainEventDispatcherService::class);
        $this->eventStatisticsCancellationService = m::mock(EventStatisticsCancellationService::class);

        $this->service = new OrderCancelService(
            mailer: $this->mailer,
            attendeeRepository: $this->attendeeRepository,
            eventRepository: $this->eventRepository,
            orderRepository: $this->orderRepository,
            databaseManager: $this->databaseManager,
            productQuantityService: $this->productQuantityService,
            domainEventDispatcherService: $this->domainEventDispatcherService,
            eventStatisticsCancellationService: $this->eventStatisticsCancellationService,
        );
    }

    public function testCancelOrder(): void
    {
        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn(1);
        $order->shouldReceive('getId')->andReturn(1);
        $order->shouldReceive('getEmail')->andReturn('customer@example.com');
        $order->shouldReceive('isOrderAwaitingOfflinePayment')->andReturn(false);

        $order->shouldReceive('getLocale')->andReturn('en');

        $attendees = new Collection([
            m::mock(AttendeeDomainObject::class)->shouldReceive('getproductPriceId')->andReturn(1)->mock(),
            m::mock(AttendeeDomainObject::class)->shouldReceive('getproductPriceId')->andReturn(2)->mock(),
        ]);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with([
                'order_id' => $order->getId(),
            ])
            ->andReturn($attendees);

        $this->attendeeRepository->shouldReceive('updateWhere')->once();

        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->twice();

        $this->orderRepository->shouldReceive('updateWhere')->once();

        $this->eventStatisticsCancellationService->shouldReceive('decrementForCancelledOrder')
            ->once()
            ->with($order);

        $event = new EventDomainObject();
        $event->setEventSettings(new EventSettingDomainObject());
        $event->setOrganizer(new OrganizerDomainObject());
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

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->withArgs(function (OrderEvent $event) use ($order) {
                return $event->type === DomainEventType::ORDER_CANCELLED
                    && $event->orderId === $order->getId();
            })
            ->once();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            $callback();
        });

        $attendees->each(function ($attendee) {
            $attendee->shouldReceive('getStatus')->andReturn(AttendeeStatus::ACTIVE->name);
        });

        try {
            $this->service->cancelOrder($order);
        } catch (Throwable $e) {
            $this->fail("Failed to cancel order: " . $e->getMessage());
        }

        $this->assertTrue(true, "Order cancellation proceeded without throwing an exception.");
    }

    public function testCancelOrderAwaitingOfflinePayment(): void
    {
        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn(1);
        $order->shouldReceive('getId')->andReturn(1);
        $order->shouldReceive('getEmail')->andReturn('customer@example.com');
        $order->shouldReceive('isOrderAwaitingOfflinePayment')->andReturn(true);
        $order->shouldReceive('getLocale')->andReturn('en');

        $attendees = new Collection([
            m::mock(AttendeeDomainObject::class)->shouldReceive('getproductPriceId')->andReturn(1)->mock(),
            m::mock(AttendeeDomainObject::class)->shouldReceive('getproductPriceId')->andReturn(2)->mock(),
        ]);

        $this->attendeeRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with([
                'order_id' => $order->getId(),
            ])
            ->andReturn($attendees);

        $this->attendeeRepository->shouldReceive('updateWhere')->once();

        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->twice();

        $this->orderRepository->shouldReceive('updateWhere')->once();

        $this->eventStatisticsCancellationService->shouldReceive('decrementForCancelledOrder')
            ->once()
            ->with($order);

        $event = new EventDomainObject();
        $event->setEventSettings(new EventSettingDomainObject());
        $event->setOrganizer(new OrganizerDomainObject());
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

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->withArgs(function (OrderEvent $event) use ($order) {
                return $event->type === DomainEventType::ORDER_CANCELLED
                    && $event->orderId === $order->getId();
            })
            ->once();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            $callback();
        });

        $attendees->each(function ($attendee) {
            $attendee->shouldReceive('getStatus')->andReturn(AttendeeStatus::AWAITING_PAYMENT->name);
        });

        try {
            $this->service->cancelOrder($order);
        } catch (Throwable $e) {
            $this->fail("Failed to cancel order: " . $e->getMessage());
        }

        $this->assertTrue(true, "Order cancellation proceeded without throwing an exception.");
    }
}
