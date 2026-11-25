<?php

namespace Tests\Unit\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\Attendee\AttendeeCredentialRotationService;
use HiEvents\Services\Domain\Attendee\AttendeeDetailsChangeNotificationService;
use HiEvents\Services\Domain\Attendee\DTO\AttendeeCredentialsDTO;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\SelfService\SelfServiceEditAttendeeService;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferInitiationService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SelfServiceEditAttendeeServiceTest extends TestCase
{
    private const ATTENDEE_ID = 456;

    private const EVENT_ID = 789;

    private SelfServiceEditAttendeeService $service;

    private MockInterface|AttendeeRepositoryInterface $attendeeRepository;

    private MockInterface|OrderAuditLogService $orderAuditLogService;

    private MockInterface|AttendeeCredentialRotationService $credentialRotationService;

    private MockInterface|AttendeeDetailsChangeNotificationService $changeNotificationService;

    private MockInterface|DomainEventDispatcherService $domainEventDispatcherService;

    private MockInterface|TicketTransferInitiationService $transferInitiationService;

    private MockInterface|TicketResaleListingRepositoryInterface $listingRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->orderAuditLogService = Mockery::mock(OrderAuditLogService::class);
        $this->credentialRotationService = Mockery::mock(AttendeeCredentialRotationService::class);
        $this->changeNotificationService = Mockery::mock(AttendeeDetailsChangeNotificationService::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);
        $this->transferInitiationService = Mockery::mock(TicketTransferInitiationService::class);
        $this->transferInitiationService->shouldNotReceive('cancelForHolderChange')->byDefault();
        $this->listingRepository = Mockery::mock(TicketResaleListingRepositoryInterface::class);
        $this->listingRepository->shouldReceive('countWhere')->andReturn(0)->byDefault();

        $this->service = new SelfServiceEditAttendeeService(
            $this->attendeeRepository,
            $this->orderAuditLogService,
            $this->credentialRotationService,
            $this->changeNotificationService,
            $this->domainEventDispatcherService,
            $this->transferInitiationService,
            $this->listingRepository,
        );
    }

    public function test_a_name_edit_keeps_the_ticket_credentials_and_tells_integrations(): void
    {
        $attendee = $this->attendee();

        $this->credentialRotationService->shouldNotReceive('issueFor');
        $this->attendeeRepository->shouldReceive('updateWhere')->once()
            ->with(['first_name' => 'Jane', 'last_name' => 'Smith'], ['id' => self::ATTENDEE_ID]);
        $this->changeNotificationService->shouldReceive('notify')->once()->with(
            self::ATTENDEE_ID,
            self::EVENT_ID,
            'old@example.com',
            false,
            ['first_name' => 'John', 'last_name' => 'Doe'],
            ['first_name' => 'Jane', 'last_name' => 'Smith'],
        );
        $this->orderAuditLogService->shouldReceive('logAttendeeUpdate')->once()->with(
            $attendee,
            ['first_name' => 'John', 'last_name' => 'Doe'],
            ['first_name' => 'Jane', 'last_name' => 'Smith'],
            '192.168.1.1',
            'Mozilla/5.0',
        );
        $this->expectAttendeeUpdatedEvent();

        $result = $this->service->editAttendee($attendee, 'Jane', 'Smith', null, '192.168.1.1', 'Mozilla/5.0');

        $this->assertTrue($result->success);
        $this->assertFalse($result->shortIdChanged);
        $this->assertNull($result->newShortId);
        $this->assertFalse($result->emailChanged);
    }

    public function test_an_email_change_replaces_the_ticket_link_and_the_qr_value(): void
    {
        $attendee = $this->attendee();

        $this->transferInitiationService->shouldReceive('cancelForHolderChange')->once()->with(self::ATTENDEE_ID);

        $this->credentialRotationService->shouldReceive('issueFor')->once()->with(self::EVENT_ID)
            ->andReturn(new AttendeeCredentialsDTO(short_id: 'a_new', public_id: 'A-NEW'));
        $this->attendeeRepository->shouldReceive('updateWhere')->once()->with(
            ['email' => 'new@example.com', 'short_id' => 'a_new', 'public_id' => 'A-NEW'],
            ['id' => self::ATTENDEE_ID],
        );
        $this->changeNotificationService->shouldReceive('notify')->once()->with(
            self::ATTENDEE_ID,
            self::EVENT_ID,
            'old@example.com',
            true,
            ['email' => 'old@example.com'],
            ['email' => 'new@example.com'],
        );
        $this->orderAuditLogService->shouldReceive('logAttendeeUpdate')->once()->with(
            $attendee,
            ['email' => 'old@example.com'],
            ['email' => 'new@example.com', 'credentials_replaced' => true],
            '192.168.1.1',
            'Mozilla/5.0',
        );
        $this->expectAttendeeUpdatedEvent();

        $result = $this->service->editAttendee($attendee, null, null, 'new@example.com', '192.168.1.1', 'Mozilla/5.0');

        $this->assertTrue($result->shortIdChanged);
        $this->assertSame('a_new', $result->newShortId);
        $this->assertTrue($result->emailChanged);
    }

    public function test_nothing_happens_when_no_field_changes(): void
    {
        $this->attendeeRepository->shouldNotReceive('updateWhere');
        $this->changeNotificationService->shouldNotReceive('notify');
        $this->orderAuditLogService->shouldNotReceive('logAttendeeUpdate');
        $this->domainEventDispatcherService->shouldNotReceive('dispatch');

        $result = $this->service->editAttendee($this->attendee(), 'John', 'Doe', 'old@example.com', '192.168.1.1', 'Mozilla/5.0');

        $this->assertTrue($result->success);
        $this->assertFalse($result->emailChanged);
        $this->assertFalse($result->shortIdChanged);
    }

    private function attendee(): AttendeeDomainObject
    {
        return (new AttendeeDomainObject)
            ->setId(self::ATTENDEE_ID)
            ->setEventId(self::EVENT_ID)
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setEmail('old@example.com')
            ->setShortId('a_oldshortid123')
            ->setPublicId('A-OLD');
    }

    private function expectAttendeeUpdatedEvent(): void
    {
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once()->withArgs(
            fn (AttendeeEvent $event) => $event->type === DomainEventType::ATTENDEE_UPDATED
                && $event->attendeeId === self::ATTENDEE_ID,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
