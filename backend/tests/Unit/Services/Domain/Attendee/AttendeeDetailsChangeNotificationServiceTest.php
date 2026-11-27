<?php

namespace Tests\Unit\Services\Domain\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Helper\EmailMasker;
use HiEvents\Mail\Attendee\AttendeeDetailsChangedMail;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Attendee\AttendeeDetailsChangeNotificationService;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AttendeeDetailsChangeNotificationServiceTest extends TestCase
{
    private const ATTENDEE_ID = 456;

    private const EVENT_ID = 789;

    private MockInterface|AttendeeRepositoryInterface $attendeeRepository;

    private MockInterface|EventRepositoryInterface $eventRepository;

    private MockInterface|SendAttendeeTicketService $sendAttendeeTicketService;

    private AttendeeDetailsChangeNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->sendAttendeeTicketService = Mockery::mock(SendAttendeeTicketService::class);

        $this->service = new AttendeeDetailsChangeNotificationService(
            $this->attendeeRepository,
            $this->eventRepository,
            $this->sendAttendeeTicketService,
        );
    }

    public function test_a_name_change_tells_the_address_on_file_and_sends_no_new_ticket(): void
    {
        $this->loadsEventAndAttendee();
        $this->sendAttendeeTicketService->shouldNotReceive('send');

        $this->service->notify(self::ATTENDEE_ID, self::EVENT_ID, 'old@example.com', false, ['first_name' => 'John'], ['first_name' => 'Jane']);

        Mail::assertQueued(AttendeeDetailsChangedMail::class, fn ($mail) => $mail->hasTo('old@example.com'));
    }

    public function test_an_email_change_sends_the_ticket_to_the_new_address_and_tells_the_old_one(): void
    {
        $this->loadsEventAndAttendee();
        $this->sendAttendeeTicketService->shouldReceive('send')->once();

        $this->service->notify(self::ATTENDEE_ID, self::EVENT_ID, 'old@example.com', true, ['email' => 'old@example.com'], ['email' => 'new@example.com']);

        Mail::assertQueued(AttendeeDetailsChangedMail::class, fn ($mail) => $mail->hasTo('old@example.com'));
    }

    public function test_the_old_address_learns_the_ticket_moved_but_not_who_holds_it_now(): void
    {
        $this->loadsEventAndAttendee();
        $this->sendAttendeeTicketService->shouldReceive('send')->once();

        $this->service->notify(
            self::ATTENDEE_ID,
            self::EVENT_ID,
            'old@example.com',
            true,
            ['email' => 'old@example.com', 'first_name' => 'John', 'last_name' => 'Doe'],
            ['email' => 'jane.roe@example.com', 'first_name' => 'Jane', 'last_name' => 'Roe'],
        );

        Mail::assertQueued(AttendeeDetailsChangedMail::class, fn (AttendeeDetailsChangedMail $mail) => $mail->content()->with['changedFields'] === [
            __('Email') => ['old' => 'old@example.com', 'new' => EmailMasker::mask('jane.roe@example.com')],
        ]);
    }

    public function test_a_name_change_shows_the_old_and_new_name_to_the_holder(): void
    {
        $this->loadsEventAndAttendee();

        $this->service->notify(self::ATTENDEE_ID, self::EVENT_ID, 'old@example.com', false, ['first_name' => 'John'], ['first_name' => 'Jane']);

        Mail::assertQueued(AttendeeDetailsChangedMail::class, fn (AttendeeDetailsChangedMail $mail) => $mail->content()->with['changedFields'] === [
            __('First Name') => ['old' => 'John', 'new' => 'Jane'],
        ]);
    }

    public function test_a_ticket_gaining_its_first_email_gets_the_ticket_and_no_change_notice(): void
    {
        $this->loadsEventAndAttendee();
        $this->sendAttendeeTicketService->shouldReceive('send')->once();

        $this->service->notify(self::ATTENDEE_ID, self::EVENT_ID, null, true, ['email' => null], ['email' => 'new@example.com']);

        Mail::assertNotQueued(AttendeeDetailsChangedMail::class);
    }

    public function test_a_name_change_on_a_ticket_without_an_email_sends_nothing(): void
    {
        $this->eventRepository->shouldNotReceive('findById');
        $this->attendeeRepository->shouldNotReceive('findById');
        $this->sendAttendeeTicketService->shouldNotReceive('send');

        $this->service->notify(self::ATTENDEE_ID, self::EVENT_ID, null, false, ['first_name' => 'Walk-up'], ['first_name' => 'Jane']);

        Mail::assertNotQueued(AttendeeDetailsChangedMail::class);
    }

    private function loadsEventAndAttendee(): void
    {
        $eventSettings = Mockery::mock(EventSettingDomainObject::class);
        $eventSettings->shouldReceive('getSupportEmail')->andReturn('support@example.com');

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);
        $event->shouldReceive('getOrganizer')->andReturn(Mockery::mock(OrganizerDomainObject::class));

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->with(self::EVENT_ID)->andReturn($event);

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getTitle')->andReturn('General Admission');

        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getOrder')->andReturn(Mockery::mock(OrderDomainObject::class));
        $attendee->shouldReceive('getProduct')->andReturn($product);

        $this->attendeeRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->attendeeRepository->shouldReceive('findById')->with(self::ATTENDEE_ID)->andReturn($attendee);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
