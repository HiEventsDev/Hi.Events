<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Mail\Order\OrderCancelled;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\Ticket\TicketQuantityUpdateService;
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
    private TicketQuantityUpdateService $ticketQuantityService;
    private OrderCancelService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = m::mock(Mailer::class);
        $this->attendeeRepository = m::mock(AttendeeRepositoryInterface::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->orderRepository = m::mock(OrderRepositoryInterface::class);
        $this->databaseManager = m::mock(DatabaseManager::class);
        $this->ticketQuantityService = m::mock(TicketQuantityUpdateService::class);

        $this->service = new OrderCancelService(
            mailer: $this->mailer,
            attendeeRepository: $this->attendeeRepository,
            eventRepository: $this->eventRepository,
            orderRepository: $this->orderRepository,
            databaseManager: $this->databaseManager,
            ticketQuantityService: $this->ticketQuantityService,
        );
    }

    public function testCancelOrder(): void
    {
        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn(1);
        $order->shouldReceive('getId')->andReturn(1);
        $order->shouldReceive('getEmail')->andReturn('customer@example.com');
        $order->shouldReceive('getLocale')->andReturn('en');

        $attendees = new Collection([
            m::mock(AttendeeDomainObject::class)->shouldReceive('getTicketPriceId')->andReturn(1)->mock(),
            m::mock(AttendeeDomainObject::class)->shouldReceive('getTicketPriceId')->andReturn(2)->mock(),
        ]);

        $this->attendeeRepository->shouldReceive('findWhere')->once()->andReturn($attendees);
        $this->attendeeRepository->shouldReceive('updateWhere')->once();

        $this->ticketQuantityService->shouldReceive('decreaseQuantitySold')->twice();

        $this->orderRepository->shouldReceive('updateWhere')->once();

        $event = new EventDomainObject();
        $event->setEventSettings(new EventSettingDomainObject());
        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->once()
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

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            $callback();
        });

        try {
            $this->service->cancelOrder($order);
        } catch (Throwable $e) {
            $this->fail("Failed to cancel order: " . $e->getMessage());
        }

        $this->assertTrue(true, "Order cancellation proceeded without throwing an exception.");
    }
}
