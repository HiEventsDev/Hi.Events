<?php

namespace Tests\Unit\Services\Domain\SelfService;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Order\OrderDetailsChangedMail;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\SelfService\SelfServiceEditOrderService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SelfServiceEditOrderServiceTest extends TestCase
{
    private SelfServiceEditOrderService $service;
    private MockInterface|OrderRepositoryInterface $orderRepository;
    private MockInterface|EventRepositoryInterface $eventRepository;
    private MockInterface|OrderAuditLogService $orderAuditLogService;
    private MockInterface|SendOrderDetailsService $sendOrderDetailsService;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->orderAuditLogService = Mockery::mock(OrderAuditLogService::class);
        $this->sendOrderDetailsService = Mockery::mock(SendOrderDetailsService::class);

        $this->service = new SelfServiceEditOrderService(
            $this->orderRepository,
            $this->eventRepository,
            $this->orderAuditLogService,
            $this->sendOrderDetailsService
        );
    }

    public function testSuccessfulEditUpdatesOrderFields(): void
    {
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);
        $order->shouldReceive('getEventId')->andReturn(789);
        $order->shouldReceive('getFirstName')->andReturn('John');
        $order->shouldReceive('getLastName')->andReturn('Doe');
        $order->shouldReceive('getEmail')->andReturn('old@example.com');

        $this->orderRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function ($attributes, $where) {
                return $attributes === [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                ]
                && $where === ['id' => 123];
            })
            ->andReturn(1);

        $mockEventSettings = Mockery::mock(EventSettingDomainObject::class);
        $mockEventSettings->shouldReceive('getSupportEmail')->andReturn('support@example.com');
        $mockOrganizer = Mockery::mock(OrganizerDomainObject::class);
        $mockEvent = Mockery::mock(EventDomainObject::class);
        $mockEvent->shouldReceive('getEventSettings')->andReturn($mockEventSettings);
        $mockEvent->shouldReceive('getOrganizer')->andReturn($mockOrganizer);

        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->eventRepository
            ->shouldReceive('findById')
            ->with(789)
            ->andReturn($mockEvent);

        $this->orderAuditLogService
            ->shouldReceive('logOrderUpdate')
            ->once()
            ->withArgs(function ($ord, $oldValues, $newValues, $ip, $ua) use ($order) {
                return $ord === $order
                    && $oldValues === ['first_name' => 'John', 'last_name' => 'Doe']
                    && $newValues === ['first_name' => 'Jane', 'last_name' => 'Smith']
                    && $ip === '192.168.1.1'
                    && $ua === 'Mozilla/5.0';
            });

        $result = $this->service->editOrder(
            order: $order,
            firstName: 'Jane',
            lastName: 'Smith',
            email: null,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue($result->success);
        $this->assertFalse($result->shortIdChanged);
        $this->assertNull($result->newShortId);
        $this->assertFalse($result->emailChanged);

        Mail::assertQueued(OrderDetailsChangedMail::class, function ($mail) {
            return $mail->hasTo('old@example.com');
        });
    }

    public function testEmailChangeTriggersShortIdRotation(): void
    {
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);
        $order->shouldReceive('getEventId')->andReturn(789);
        $order->shouldReceive('getFirstName')->andReturn('John');
        $order->shouldReceive('getLastName')->andReturn('Doe');
        $order->shouldReceive('getEmail')->andReturn('old@example.com');
        $order->shouldReceive('getShortId')->andReturn('o_oldshortid123');

        $this->orderRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function ($attributes, $where) {
                return isset($attributes['email'])
                    && $attributes['email'] === 'new@example.com'
                    && isset($attributes['short_id'])
                    && str_starts_with($attributes['short_id'], 'o_')
                    && $where === ['id' => 123];
            })
            ->andReturn(1);

        $mockOrderWithRelations = Mockery::mock(OrderDomainObject::class);
        $mockOrderWithRelations->shouldReceive('getLatestInvoice')->andReturn(null);

        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with(123)
            ->andReturn($mockOrderWithRelations);

        $mockEventSettings = Mockery::mock(EventSettingDomainObject::class);
        $mockOrganizer = Mockery::mock(OrganizerDomainObject::class);
        $mockEvent = Mockery::mock(EventDomainObject::class);
        $mockEvent->shouldReceive('getEventSettings')->andReturn($mockEventSettings);
        $mockEvent->shouldReceive('getOrganizer')->andReturn($mockOrganizer);

        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->eventRepository
            ->shouldReceive('findById')
            ->with(789)
            ->andReturn($mockEvent);

        $this->sendOrderDetailsService
            ->shouldReceive('sendCustomerOrderSummary')
            ->once();

        $this->orderAuditLogService
            ->shouldReceive('logOrderUpdate')
            ->once()
            ->withArgs(function ($ord, $oldValues, $newValues, $ip, $ua) use ($order) {
                return $ord === $order
                    && isset($oldValues['email']) && $oldValues['email'] === 'old@example.com'
                    && isset($oldValues['short_id'])
                    && isset($newValues['email']) && $newValues['email'] === 'new@example.com'
                    && isset($newValues['short_id']) && str_starts_with($newValues['short_id'], 'o_')
                    && $ip === '192.168.1.1'
                    && $ua === 'Mozilla/5.0';
            });

        $result = $this->service->editOrder(
            order: $order,
            firstName: null,
            lastName: null,
            email: 'new@example.com',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue($result->success);
        $this->assertTrue($result->shortIdChanged);
        $this->assertNotNull($result->newShortId);
        $this->assertStringStartsWith('o_', $result->newShortId);
        $this->assertTrue($result->emailChanged);
    }

    public function testNoUpdateWhenNoFieldsChange(): void
    {
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);
        $order->shouldReceive('getFirstName')->andReturn('John');
        $order->shouldReceive('getLastName')->andReturn('Doe');
        $order->shouldReceive('getEmail')->andReturn('same@example.com');

        $this->orderRepository->shouldReceive('updateWhere')->never();
        $this->orderAuditLogService->shouldReceive('logOrderUpdate')->never();

        $result = $this->service->editOrder(
            order: $order,
            firstName: 'John',
            lastName: 'Doe',
            email: 'same@example.com',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue($result->success);
        $this->assertFalse($result->shortIdChanged);
        $this->assertNull($result->newShortId);
        $this->assertFalse($result->emailChanged);

        Mail::assertNothingSent();
    }

    public function testMultipleFieldsUpdateTogether(): void
    {
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);
        $order->shouldReceive('getEventId')->andReturn(789);
        $order->shouldReceive('getFirstName')->andReturn('John');
        $order->shouldReceive('getLastName')->andReturn('Doe');
        $order->shouldReceive('getEmail')->andReturn('old@example.com');
        $order->shouldReceive('getShortId')->andReturn('o_oldshortid123');

        $this->orderRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function ($attributes, $where) {
                return isset($attributes['first_name'])
                    && $attributes['first_name'] === 'Jane'
                    && isset($attributes['last_name'])
                    && $attributes['last_name'] === 'Smith'
                    && isset($attributes['email'])
                    && $attributes['email'] === 'new@example.com'
                    && isset($attributes['short_id'])
                    && str_starts_with($attributes['short_id'], 'o_')
                    && $where === ['id' => 123];
            })
            ->andReturn(1);

        $mockOrderWithRelations = Mockery::mock(OrderDomainObject::class);
        $mockOrderWithRelations->shouldReceive('getLatestInvoice')->andReturn(null);

        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with(123)
            ->andReturn($mockOrderWithRelations);

        $mockEventSettings = Mockery::mock(EventSettingDomainObject::class);
        $mockOrganizer = Mockery::mock(OrganizerDomainObject::class);
        $mockEvent = Mockery::mock(EventDomainObject::class);
        $mockEvent->shouldReceive('getEventSettings')->andReturn($mockEventSettings);
        $mockEvent->shouldReceive('getOrganizer')->andReturn($mockOrganizer);

        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->eventRepository
            ->shouldReceive('findById')
            ->with(789)
            ->andReturn($mockEvent);

        $this->sendOrderDetailsService
            ->shouldReceive('sendCustomerOrderSummary')
            ->once();

        $this->orderAuditLogService
            ->shouldReceive('logOrderUpdate')
            ->once()
            ->withArgs(function ($ord, $oldValues, $newValues, $ip, $ua) use ($order) {
                return $ord === $order
                    && $oldValues['first_name'] === 'John'
                    && $oldValues['last_name'] === 'Doe'
                    && $oldValues['email'] === 'old@example.com'
                    && isset($oldValues['short_id'])
                    && $newValues['first_name'] === 'Jane'
                    && $newValues['last_name'] === 'Smith'
                    && $newValues['email'] === 'new@example.com'
                    && isset($newValues['short_id']) && str_starts_with($newValues['short_id'], 'o_')
                    && $ip === '192.168.1.1'
                    && $ua === 'Mozilla/5.0';
            });

        $result = $this->service->editOrder(
            order: $order,
            firstName: 'Jane',
            lastName: 'Smith',
            email: 'new@example.com',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue($result->success);
        $this->assertTrue($result->shortIdChanged);
        $this->assertNotNull($result->newShortId);
        $this->assertStringStartsWith('o_', $result->newShortId);
        $this->assertTrue($result->emailChanged);
    }

    public function testOnlyEmailUpdate(): void
    {
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);
        $order->shouldReceive('getEventId')->andReturn(789);
        $order->shouldReceive('getFirstName')->andReturn('John');
        $order->shouldReceive('getLastName')->andReturn('Doe');
        $order->shouldReceive('getEmail')->andReturn('old@example.com');
        $order->shouldReceive('getShortId')->andReturn('o_oldshortid123');

        $this->orderRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function ($attributes, $where) {
                return isset($attributes['email'])
                    && $attributes['email'] === 'new@example.com'
                    && isset($attributes['short_id'])
                    && str_starts_with($attributes['short_id'], 'o_')
                    && $where === ['id' => 123];
            })
            ->andReturn(1);

        $mockOrderWithRelations = Mockery::mock(OrderDomainObject::class);
        $mockOrderWithRelations->shouldReceive('getLatestInvoice')->andReturn(null);

        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findById')
            ->with(123)
            ->andReturn($mockOrderWithRelations);

        $mockEventSettings = Mockery::mock(EventSettingDomainObject::class);
        $mockOrganizer = Mockery::mock(OrganizerDomainObject::class);
        $mockEvent = Mockery::mock(EventDomainObject::class);
        $mockEvent->shouldReceive('getEventSettings')->andReturn($mockEventSettings);
        $mockEvent->shouldReceive('getOrganizer')->andReturn($mockOrganizer);

        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->eventRepository
            ->shouldReceive('findById')
            ->with(789)
            ->andReturn($mockEvent);

        $this->sendOrderDetailsService
            ->shouldReceive('sendCustomerOrderSummary')
            ->once();

        $this->orderAuditLogService
            ->shouldReceive('logOrderUpdate')
            ->once();

        $result = $this->service->editOrder(
            order: $order,
            firstName: null,
            lastName: null,
            email: 'new@example.com',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue($result->success);
        $this->assertTrue($result->shortIdChanged);
        $this->assertNotNull($result->newShortId);
        $this->assertStringStartsWith('o_', $result->newShortId);
        $this->assertTrue($result->emailChanged);
    }

    public function testOnlyFirstNameUpdate(): void
    {
        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);
        $order->shouldReceive('getEventId')->andReturn(789);
        $order->shouldReceive('getFirstName')->andReturn('John');
        $order->shouldReceive('getLastName')->andReturn('Doe');
        $order->shouldReceive('getEmail')->andReturn('same@example.com');

        $this->orderRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function ($attributes, $where) {
                return $attributes === ['first_name' => 'Jane']
                    && $where === ['id' => 123];
            })
            ->andReturn(1);

        $mockEventSettings = Mockery::mock(EventSettingDomainObject::class);
        $mockEventSettings->shouldReceive('getSupportEmail')->andReturn('support@example.com');
        $mockOrganizer = Mockery::mock(OrganizerDomainObject::class);
        $mockEvent = Mockery::mock(EventDomainObject::class);
        $mockEvent->shouldReceive('getEventSettings')->andReturn($mockEventSettings);
        $mockEvent->shouldReceive('getOrganizer')->andReturn($mockOrganizer);

        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->eventRepository
            ->shouldReceive('findById')
            ->with(789)
            ->andReturn($mockEvent);

        $this->orderAuditLogService
            ->shouldReceive('logOrderUpdate')
            ->once();

        $result = $this->service->editOrder(
            order: $order,
            firstName: 'Jane',
            lastName: null,
            email: null,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue($result->success);
        $this->assertFalse($result->shortIdChanged);
        $this->assertNull($result->newShortId);
        $this->assertFalse($result->emailChanged);

        Mail::assertQueued(OrderDetailsChangedMail::class, function ($mail) {
            return $mail->hasTo('same@example.com');
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
