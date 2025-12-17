<?php

namespace Tests\Unit\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\SelfService\SelfServiceResendEmailService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SelfServiceResendEmailServiceTest extends TestCase
{
    private SelfServiceResendEmailService $service;
    private MockInterface|SendAttendeeTicketService $sendAttendeeTicketService;
    private MockInterface|SendOrderDetailsService $sendOrderDetailsService;
    private MockInterface|AttendeeRepositoryInterface $attendeeRepository;
    private MockInterface|OrderRepositoryInterface $orderRepository;
    private MockInterface|EventRepositoryInterface $eventRepository;
    private MockInterface|OrderAuditLogService $orderAuditLogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sendAttendeeTicketService = Mockery::mock(SendAttendeeTicketService::class);
        $this->sendOrderDetailsService = Mockery::mock(SendOrderDetailsService::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->orderAuditLogService = Mockery::mock(OrderAuditLogService::class);

        $this->service = new SelfServiceResendEmailService(
            $this->sendAttendeeTicketService,
            $this->sendOrderDetailsService,
            $this->attendeeRepository,
            $this->orderRepository,
            $this->eventRepository,
            $this->orderAuditLogService
        );
    }

    public function testResendAttendeeTicketSuccessfully(): void
    {
        $attendeeId = 456;
        $orderId = 123;
        $eventId = 1;

        $order = Mockery::mock(OrderDomainObject::class);
        $orderItems = Mockery::mock(OrderItemDomainObject::class);
        $order->shouldReceive('getOrderItems')->andReturn(collect([$orderItems]));

        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getOrder')->andReturn($order);

        $eventSettings = Mockery::mock(EventSettingDomainObject::class);
        $organizer = Mockery::mock(OrganizerDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);
        $event->shouldReceive('getOrganizer')->andReturn($organizer);

        $this->attendeeRepository
            ->shouldReceive('loadRelation')
            ->once()
            ->with(Mockery::type(Relationship::class))
            ->andReturnSelf();

        $this->attendeeRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $attendeeId,
                'order_id' => $orderId,
                'event_id' => $eventId,
            ])
            ->andReturn($attendee);

        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->twice()
            ->andReturnSelf();

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->sendAttendeeTicketService
            ->shouldReceive('send')
            ->once()
            ->withArgs(function ($ord, $att, $evt, $evtSettings, $org) use ($order, $attendee, $event, $eventSettings, $organizer) {
                return $ord === $order
                    && $att === $attendee
                    && $evt === $event
                    && $evtSettings === $eventSettings
                    && $org === $organizer;
            });

        $this->orderAuditLogService
            ->shouldReceive('logEmailResent')
            ->once()
            ->withArgs(function ($action, $evtId, $ordId, $attId, $ip, $ua) use ($eventId, $orderId, $attendeeId) {
                return $action === OrderAuditAction::ATTENDEE_EMAIL_RESENT->value
                    && $evtId === $eventId
                    && $ordId === $orderId
                    && $attId === $attendeeId
                    && $ip === '192.168.1.1'
                    && $ua === 'Mozilla/5.0';
            });

        $this->service->resendAttendeeTicket(
            attendeeId: $attendeeId,
            orderId: $orderId,
            eventId: $eventId,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue(true);
    }

    public function testResendOrderConfirmationSuccessfully(): void
    {
        $orderId = 123;
        $eventId = 1;

        $orderItems = Mockery::mock(OrderItemDomainObject::class);
        $attendees = Mockery::mock(AttendeeDomainObject::class);
        $invoice = Mockery::mock(InvoiceDomainObject::class);

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getOrderItems')->andReturn(collect([$orderItems]));
        $order->shouldReceive('getAttendees')->andReturn(collect([$attendees]));
        $order->shouldReceive('getLatestInvoice')->andReturn($invoice);

        $eventSettings = Mockery::mock(EventSettingDomainObject::class);
        $organizer = Mockery::mock(OrganizerDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);
        $event->shouldReceive('getOrganizer')->andReturn($organizer);

        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->times(3)
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $orderId,
                'event_id' => $eventId,
            ])
            ->andReturn($order);

        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->twice()
            ->andReturnSelf();

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->sendOrderDetailsService
            ->shouldReceive('sendCustomerOrderSummary')
            ->once()
            ->withArgs(function ($ord, $evt, $org, $evtSettings, $inv) use ($order, $event, $organizer, $eventSettings, $invoice) {
                return $ord === $order
                    && $evt === $event
                    && $org === $organizer
                    && $evtSettings === $eventSettings
                    && $inv === $invoice;
            });

        $this->orderAuditLogService
            ->shouldReceive('logEmailResent')
            ->once()
            ->withArgs(function ($action, $evtId, $ordId, $attId, $ip, $ua) use ($eventId, $orderId) {
                return $action === OrderAuditAction::ORDER_EMAIL_RESENT->value
                    && $evtId === $eventId
                    && $ordId === $orderId
                    && $attId === null
                    && $ip === '192.168.1.1'
                    && $ua === 'Mozilla/5.0';
            });

        $this->service->resendOrderConfirmation(
            orderId: $orderId,
            eventId: $eventId,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue(true);
    }

    public function testResendAttendeeTicketLoadsCorrectRelationships(): void
    {
        $attendeeId = 456;
        $orderId = 123;
        $eventId = 1;

        $order = Mockery::mock(OrderDomainObject::class);
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getOrder')->andReturn($order);

        $eventSettings = Mockery::mock(EventSettingDomainObject::class);
        $organizer = Mockery::mock(OrganizerDomainObject::class);
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);
        $event->shouldReceive('getOrganizer')->andReturn($organizer);

        $this->attendeeRepository
            ->shouldReceive('loadRelation')
            ->once()
            ->with(Mockery::on(function ($relationship) {
                return $relationship instanceof Relationship
                    && $relationship->getDomainObject() === OrderDomainObject::class
                    && $relationship->getName() === 'order';
            }))
            ->andReturnSelf();

        $this->attendeeRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($attendee);

        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->once()
            ->with(Mockery::on(function ($relationship) {
                return $relationship instanceof Relationship
                    && $relationship->getDomainObject() === OrganizerDomainObject::class;
            }))
            ->andReturnSelf();

        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->once()
            ->with(EventSettingDomainObject::class)
            ->andReturnSelf();

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($event);

        $this->sendAttendeeTicketService
            ->shouldReceive('send')
            ->once();

        $this->orderAuditLogService
            ->shouldReceive('logEmailResent')
            ->once();

        $this->service->resendAttendeeTicket(
            attendeeId: $attendeeId,
            orderId: $orderId,
            eventId: $eventId,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue(true);
    }

    public function testResendOrderConfirmationLoadsCorrectRelationships(): void
    {
        $orderId = 123;
        $eventId = 1;

        $invoice = Mockery::mock(InvoiceDomainObject::class);
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getLatestInvoice')->andReturn($invoice);

        $eventSettings = Mockery::mock(EventSettingDomainObject::class);
        $organizer = Mockery::mock(OrganizerDomainObject::class);
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);
        $event->shouldReceive('getOrganizer')->andReturn($organizer);

        $loadRelationCallCount = 0;
        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->times(3)
            ->with(Mockery::on(function ($domainObject) use (&$loadRelationCallCount) {
                $loadRelationCallCount++;
                return in_array($domainObject, [
                    OrderItemDomainObject::class,
                    AttendeeDomainObject::class,
                    InvoiceDomainObject::class,
                ]);
            }))
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($order);

        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->twice()
            ->andReturnSelf();

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($event);

        $this->sendOrderDetailsService
            ->shouldReceive('sendCustomerOrderSummary')
            ->once();

        $this->orderAuditLogService
            ->shouldReceive('logEmailResent')
            ->once();

        $this->service->resendOrderConfirmation(
            orderId: $orderId,
            eventId: $eventId,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
