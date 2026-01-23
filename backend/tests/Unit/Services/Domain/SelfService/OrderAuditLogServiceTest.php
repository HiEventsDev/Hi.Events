<?php

namespace Tests\Unit\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\OrderAuditLogDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\OrderAuditLogRepositoryInterface;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderAuditLogServiceTest extends TestCase
{
    private OrderAuditLogService $service;
    private MockInterface|OrderAuditLogRepositoryInterface $orderAuditLogRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderAuditLogRepository = Mockery::mock(OrderAuditLogRepositoryInterface::class);

        $this->service = new OrderAuditLogService(
            $this->orderAuditLogRepository
        );
    }

    public function testLogAttendeeUpdateCreatesAuditLogEntry(): void
    {
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getEventId')->andReturn(1);
        $attendee->shouldReceive('getOrderId')->andReturn(123);
        $attendee->shouldReceive('getId')->andReturn(456);

        $oldValues = [
            'first_name' => 'John',
            'email' => 'old@example.com',
        ];

        $newValues = [
            'first_name' => 'Jane',
            'email' => 'new@example.com',
        ];

        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        $auditLog = Mockery::mock(OrderAuditLogDomainObject::class);

        $this->orderAuditLogRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($attendee, $oldValues, $newValues, $ipAddress, $userAgent) {
                return $data['event_id'] === 1
                    && $data['order_id'] === 123
                    && $data['attendee_id'] === 456
                    && $data['action'] === OrderAuditAction::ATTENDEE_UPDATED->value
                    && $data['old_values'] === $oldValues
                    && $data['new_values'] === $newValues
                    && $data['changed_fields'] === 'first_name,email'
                    && $data['ip_address'] === $ipAddress
                    && $data['user_agent'] === $userAgent;
            })
            ->andReturn($auditLog);

        $this->service->logAttendeeUpdate(
            attendee: $attendee,
            oldValues: $oldValues,
            newValues: $newValues,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        $this->assertTrue(true);
    }

    public function testLogOrderUpdateCreatesAuditLogEntry(): void
    {
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getEventId')->andReturn(1);
        $order->shouldReceive('getId')->andReturn(123);

        $oldValues = [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $newValues = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ];

        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        $auditLog = Mockery::mock(OrderAuditLogDomainObject::class);

        $this->orderAuditLogRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($order, $oldValues, $newValues, $ipAddress, $userAgent) {
                return $data['event_id'] === 1
                    && $data['order_id'] === 123
                    && $data['attendee_id'] === null
                    && $data['action'] === OrderAuditAction::ORDER_UPDATED->value
                    && $data['old_values'] === $oldValues
                    && $data['new_values'] === $newValues
                    && $data['changed_fields'] === 'first_name,last_name'
                    && $data['ip_address'] === $ipAddress
                    && $data['user_agent'] === $userAgent;
            })
            ->andReturn($auditLog);

        $this->service->logOrderUpdate(
            order: $order,
            oldValues: $oldValues,
            newValues: $newValues,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        $this->assertTrue(true);
    }

    public function testLogEmailResentForAttendee(): void
    {
        $action = OrderAuditAction::ATTENDEE_EMAIL_RESENT->value;
        $eventId = 1;
        $orderId = 123;
        $attendeeId = 456;
        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        $auditLog = Mockery::mock(OrderAuditLogDomainObject::class);

        $this->orderAuditLogRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($action, $eventId, $orderId, $attendeeId, $ipAddress, $userAgent) {
                return $data['event_id'] === $eventId
                    && $data['order_id'] === $orderId
                    && $data['attendee_id'] === $attendeeId
                    && $data['action'] === $action
                    && $data['old_values'] === null
                    && $data['new_values'] === null
                    && $data['changed_fields'] === null
                    && $data['ip_address'] === $ipAddress
                    && $data['user_agent'] === $userAgent;
            })
            ->andReturn($auditLog);

        $this->service->logEmailResent(
            action: $action,
            eventId: $eventId,
            orderId: $orderId,
            attendeeId: $attendeeId,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        $this->assertTrue(true);
    }

    public function testLogEmailResentForOrder(): void
    {
        $action = OrderAuditAction::ORDER_EMAIL_RESENT->value;
        $eventId = 1;
        $orderId = 123;
        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        $auditLog = Mockery::mock(OrderAuditLogDomainObject::class);

        $this->orderAuditLogRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($action, $eventId, $orderId, $ipAddress, $userAgent) {
                return $data['event_id'] === $eventId
                    && $data['order_id'] === $orderId
                    && $data['attendee_id'] === null
                    && $data['action'] === $action
                    && $data['old_values'] === null
                    && $data['new_values'] === null
                    && $data['changed_fields'] === null
                    && $data['ip_address'] === $ipAddress
                    && $data['user_agent'] === $userAgent;
            })
            ->andReturn($auditLog);

        $this->service->logEmailResent(
            action: $action,
            eventId: $eventId,
            orderId: $orderId,
            attendeeId: null,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
