<?php

namespace Tests\Unit\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Mail\Attendee\AttendeeDetailsChangedMail;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\SelfService\SelfServiceEditAttendeeService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SelfServiceEditAttendeeServiceTest extends TestCase
{
    private SelfServiceEditAttendeeService $service;
    private MockInterface|AttendeeRepositoryInterface $attendeeRepository;
    private MockInterface|EventRepositoryInterface $eventRepository;
    private MockInterface|OrderAuditLogService $orderAuditLogService;
    private MockInterface|SendAttendeeTicketService $sendAttendeeTicketService;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->orderAuditLogService = Mockery::mock(OrderAuditLogService::class);
        $this->sendAttendeeTicketService = Mockery::mock(SendAttendeeTicketService::class);

        $this->service = new SelfServiceEditAttendeeService(
            $this->attendeeRepository,
            $this->eventRepository,
            $this->orderAuditLogService,
            $this->sendAttendeeTicketService
        );
    }

    public function testSuccessfulEditUpdatesAttendeeFields(): void
    {
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getId')->andReturn(456);
        $attendee->shouldReceive('getEventId')->andReturn(789);
        $attendee->shouldReceive('getFirstName')->andReturn('John');
        $attendee->shouldReceive('getLastName')->andReturn('Doe');
        $attendee->shouldReceive('getEmail')->andReturn('old@example.com');

        $this->attendeeRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function ($attributes, $where) {
                return $attributes === [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                ]
                && $where === ['id' => 456];
            })
            ->andReturn(1);

        $mockProduct = Mockery::mock(ProductDomainObject::class);
        $mockProduct->shouldReceive('getTitle')->andReturn('General Admission');

        $mockAttendeeWithProduct = Mockery::mock(AttendeeDomainObject::class);
        $mockAttendeeWithProduct->shouldReceive('getProduct')->andReturn($mockProduct);

        $this->attendeeRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->attendeeRepository
            ->shouldReceive('findById')
            ->with(456)
            ->andReturn($mockAttendeeWithProduct);

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
            ->shouldReceive('logAttendeeUpdate')
            ->once()
            ->withArgs(function ($att, $oldValues, $newValues, $ip, $ua) use ($attendee) {
                return $att === $attendee
                    && $oldValues === ['first_name' => 'John', 'last_name' => 'Doe']
                    && $newValues === ['first_name' => 'Jane', 'last_name' => 'Smith']
                    && $ip === '192.168.1.1'
                    && $ua === 'Mozilla/5.0';
            });

        $result = $this->service->editAttendee(
            attendee: $attendee,
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

        Mail::assertQueued(AttendeeDetailsChangedMail::class, function ($mail) {
            return $mail->hasTo('old@example.com');
        });
    }

    public function testEmailChangeTriggersShortIdRotation(): void
    {
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getId')->andReturn(456);
        $attendee->shouldReceive('getEventId')->andReturn(789);
        $attendee->shouldReceive('getFirstName')->andReturn('John');
        $attendee->shouldReceive('getLastName')->andReturn('Doe');
        $attendee->shouldReceive('getEmail')->andReturn('old@example.com');
        $attendee->shouldReceive('getShortId')->andReturn('a_oldshortid123');

        $this->attendeeRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function ($attributes, $where) {
                return isset($attributes['email'])
                    && $attributes['email'] === 'new@example.com'
                    && isset($attributes['short_id'])
                    && str_starts_with($attributes['short_id'], 'a_')
                    && $where === ['id' => 456];
            })
            ->andReturn(1);

        $mockOrder = Mockery::mock(OrderDomainObject::class);
        $mockProduct = Mockery::mock(ProductDomainObject::class);
        $mockProduct->shouldReceive('getTitle')->andReturn('General Admission');

        $mockAttendeeWithRelations = Mockery::mock(AttendeeDomainObject::class);
        $mockAttendeeWithRelations->shouldReceive('getOrder')->andReturn($mockOrder);
        $mockAttendeeWithRelations->shouldReceive('getProduct')->andReturn($mockProduct);

        $this->attendeeRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->attendeeRepository
            ->shouldReceive('findById')
            ->with(456)
            ->andReturn($mockAttendeeWithRelations);

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

        $this->sendAttendeeTicketService
            ->shouldReceive('send')
            ->once();

        $this->orderAuditLogService
            ->shouldReceive('logAttendeeUpdate')
            ->once()
            ->withArgs(function ($att, $oldValues, $newValues, $ip, $ua) use ($attendee) {
                return $att === $attendee
                    && isset($oldValues['email']) && $oldValues['email'] === 'old@example.com'
                    && isset($oldValues['short_id'])
                    && isset($newValues['email']) && $newValues['email'] === 'new@example.com'
                    && isset($newValues['short_id']) && str_starts_with($newValues['short_id'], 'a_')
                    && $ip === '192.168.1.1'
                    && $ua === 'Mozilla/5.0';
            });

        $result = $this->service->editAttendee(
            attendee: $attendee,
            firstName: null,
            lastName: null,
            email: 'new@example.com',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue($result->success);
        $this->assertTrue($result->shortIdChanged);
        $this->assertNotNull($result->newShortId);
        $this->assertStringStartsWith('a_', $result->newShortId);
        $this->assertTrue($result->emailChanged);

        Mail::assertQueued(AttendeeDetailsChangedMail::class, function ($mail) {
            return $mail->hasTo('old@example.com');
        });
    }

    public function testNoUpdateWhenNoFieldsChange(): void
    {
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getId')->andReturn(456);
        $attendee->shouldReceive('getFirstName')->andReturn('John');
        $attendee->shouldReceive('getLastName')->andReturn('Doe');
        $attendee->shouldReceive('getEmail')->andReturn('same@example.com');

        $this->attendeeRepository->shouldReceive('updateWhere')->never();
        $this->orderAuditLogService->shouldReceive('logAttendeeUpdate')->never();

        $result = $this->service->editAttendee(
            attendee: $attendee,
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
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getId')->andReturn(456);
        $attendee->shouldReceive('getEventId')->andReturn(789);
        $attendee->shouldReceive('getFirstName')->andReturn('John');
        $attendee->shouldReceive('getLastName')->andReturn('Doe');
        $attendee->shouldReceive('getEmail')->andReturn('old@example.com');
        $attendee->shouldReceive('getShortId')->andReturn('a_oldshortid123');

        $this->attendeeRepository
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
                    && str_starts_with($attributes['short_id'], 'a_')
                    && $where === ['id' => 456];
            })
            ->andReturn(1);

        $mockOrder = Mockery::mock(OrderDomainObject::class);
        $mockProduct = Mockery::mock(ProductDomainObject::class);
        $mockProduct->shouldReceive('getTitle')->andReturn('VIP Pass');

        $mockAttendeeWithRelations = Mockery::mock(AttendeeDomainObject::class);
        $mockAttendeeWithRelations->shouldReceive('getOrder')->andReturn($mockOrder);
        $mockAttendeeWithRelations->shouldReceive('getProduct')->andReturn($mockProduct);

        $this->attendeeRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->attendeeRepository
            ->shouldReceive('findById')
            ->with(456)
            ->andReturn($mockAttendeeWithRelations);

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

        $this->sendAttendeeTicketService
            ->shouldReceive('send')
            ->once();

        $this->orderAuditLogService
            ->shouldReceive('logAttendeeUpdate')
            ->once()
            ->withArgs(function ($att, $oldValues, $newValues, $ip, $ua) use ($attendee) {
                return $att === $attendee
                    && $oldValues['first_name'] === 'John'
                    && $oldValues['last_name'] === 'Doe'
                    && $oldValues['email'] === 'old@example.com'
                    && isset($oldValues['short_id'])
                    && $newValues['first_name'] === 'Jane'
                    && $newValues['last_name'] === 'Smith'
                    && $newValues['email'] === 'new@example.com'
                    && isset($newValues['short_id']) && str_starts_with($newValues['short_id'], 'a_')
                    && $ip === '192.168.1.1'
                    && $ua === 'Mozilla/5.0';
            });

        $result = $this->service->editAttendee(
            attendee: $attendee,
            firstName: 'Jane',
            lastName: 'Smith',
            email: 'new@example.com',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertTrue($result->success);
        $this->assertTrue($result->shortIdChanged);
        $this->assertNotNull($result->newShortId);
        $this->assertStringStartsWith('a_', $result->newShortId);
        $this->assertTrue($result->emailChanged);

        Mail::assertQueued(AttendeeDetailsChangedMail::class, function ($mail) {
            return $mail->hasTo('old@example.com');
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
