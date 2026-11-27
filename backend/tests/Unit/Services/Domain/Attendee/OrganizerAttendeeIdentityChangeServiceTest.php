<?php

namespace Tests\Unit\Services\Domain\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\Attendee\AttendeeCredentialRotationService;
use HiEvents\Services\Domain\Attendee\AttendeeDetailsChangeNotificationService;
use HiEvents\Services\Domain\Attendee\DTO\AttendeeCredentialsDTO;
use HiEvents\Services\Domain\Attendee\OrganizerAttendeeIdentityChangeService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferInitiationService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrganizerAttendeeIdentityChangeServiceTest extends TestCase
{
    private AttendeeCredentialRotationService|MockInterface $rotation;

    private AttendeeDetailsChangeNotificationService|MockInterface $notifications;

    private OrderAuditLogService|MockInterface $auditLog;

    private TicketTransferInitiationService|MockInterface $transferInitiationService;

    private TicketResaleListingRepositoryInterface|MockInterface $listingRepository;

    private OrganizerAttendeeIdentityChangeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rotation = Mockery::mock(AttendeeCredentialRotationService::class);
        $this->notifications = Mockery::mock(AttendeeDetailsChangeNotificationService::class);
        $this->auditLog = Mockery::mock(OrderAuditLogService::class);

        $this->transferInitiationService = Mockery::mock(TicketTransferInitiationService::class);
        $this->transferInitiationService->shouldNotReceive('cancelForHolderChange')->byDefault();

        $this->listingRepository = Mockery::mock(TicketResaleListingRepositoryInterface::class);
        $this->listingRepository->shouldReceive('countWhere')->andReturn(0)->byDefault();

        $this->service = new OrganizerAttendeeIdentityChangeService($this->rotation, $this->notifications, $this->auditLog, $this->transferInitiationService, $this->listingRepository);
    }

    public function test_an_unchanged_email_keeps_the_ticket_credentials(): void
    {
        $this->rotation->shouldNotReceive('issueFor');

        $this->assertSame([], $this->service->credentialAttributesFor($this->attendee(), 'ada@example.test'));
    }

    public function test_a_changed_email_gets_new_ticket_credentials(): void
    {
        $this->rotation->shouldReceive('issueFor')->once()->with(10)
            ->andReturn(new AttendeeCredentialsDTO(short_id: 'a_new', public_id: 'A-NEW'));

        $this->assertSame(
            ['short_id' => 'a_new', 'public_id' => 'A-NEW'],
            $this->service->credentialAttributesFor($this->attendee(), 'grace@example.test'),
        );
    }

    public function test_an_email_change_notifies_both_addresses_and_is_recorded_without_credential_values(): void
    {
        $before = $this->attendee();
        $after = $this->attendee(email: 'grace@example.test', shortId: 'a_new', publicId: 'A-NEW');

        $this->transferInitiationService->shouldReceive('cancelForHolderChange')->once()->with(30);
        $this->notifications->shouldReceive('notify')->once()
            ->with(30, 10, 'ada@example.test', true, ['email' => 'ada@example.test'], ['email' => 'grace@example.test']);
        $this->auditLog->shouldReceive('logOrganizerAttendeeUpdate')->once()
            ->with($after, ['email' => 'ada@example.test'], ['email' => 'grace@example.test', 'credentials_replaced' => true]);

        $this->assertTrue($this->service->record($before, $after));
    }

    public function test_a_name_change_is_recorded_without_emailing_anyone(): void
    {
        $before = $this->attendee();
        $after = $this->attendee(firstName: 'Augusta');

        $this->notifications->shouldNotReceive('notify');
        $this->auditLog->shouldReceive('logOrganizerAttendeeUpdate')->once()
            ->with($after, ['first_name' => 'Ada'], ['first_name' => 'Augusta']);

        $this->assertTrue($this->service->record($before, $after));
    }

    public function test_an_edit_that_leaves_name_and_email_alone_is_not_recorded(): void
    {
        $this->notifications->shouldNotReceive('notify');
        $this->auditLog->shouldNotReceive('logOrganizerAttendeeUpdate');

        $this->assertFalse($this->service->record($this->attendee(), $this->attendee()));
    }

    private function attendee(
        string $firstName = 'Ada',
        string $email = 'ada@example.test',
        string $shortId = 'a_old',
        string $publicId = 'A-OLD',
    ): AttendeeDomainObject {
        return (new AttendeeDomainObject)
            ->setId(30)
            ->setEventId(10)
            ->setOrderId(20)
            ->setFirstName($firstName)
            ->setLastName('Lovelace')
            ->setEmail($email)
            ->setShortId($shortId)
            ->setPublicId($publicId);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
