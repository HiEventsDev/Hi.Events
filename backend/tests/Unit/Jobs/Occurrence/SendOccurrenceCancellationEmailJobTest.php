<?php

namespace Tests\Unit\Jobs\Occurrence;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Occurrence\OccurrenceCancellationMail;
use HiEvents\Jobs\Occurrence\SendOccurrenceCancellationEmailJob;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Email\MailBuilderService;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Mockery;
use Tests\TestCase;

class SendOccurrenceCancellationEmailJobTest extends TestCase
{
    private EventRepositoryInterface|Mockery\MockInterface $eventRepository;
    private EventOccurrenceRepositoryInterface|Mockery\MockInterface $occurrenceRepository;
    private AttendeeRepositoryInterface|Mockery\MockInterface $attendeeRepository;
    private Mailer|Mockery\MockInterface $mailer;
    private MailBuilderService|Mockery\MockInterface $mailBuilderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->mailer = Mockery::mock(Mailer::class);
        $this->mailBuilderService = Mockery::mock(MailBuilderService::class);
        $this->mailBuilderService->shouldReceive('buildOccurrenceCancellationMail')
            ->andReturn(Mockery::mock(OccurrenceCancellationMail::class));
    }

    private function makeEvent(): EventDomainObject|Mockery\MockInterface
    {
        $organizer = Mockery::mock(OrganizerDomainObject::class);
        $eventSettings = Mockery::mock(EventSettingDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('America/New_York');
        $event->shouldReceive('getOrganizer')->andReturn($organizer);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);

        return $event;
    }

    private function makeAttendee(string $email, string $locale = 'en'): AttendeeDomainObject|Mockery\MockInterface
    {
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getEmail')->andReturn($email);
        $attendee->shouldReceive('getLocale')->andReturn($locale);
        return $attendee;
    }

    private function setupCommon(array $attendees): void
    {
        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 14:00:00');

        $this->occurrenceRepository->shouldReceive('findById')->with(10)->once()->andReturn($occurrence);
        $this->eventRepository->shouldReceive('loadRelation')->twice()->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->with(1)->once()->andReturn($this->makeEvent());
        $this->attendeeRepository->shouldReceive('findWhere')->once()->andReturn(collect($attendees));
    }

    public function testHandleSendsEmailToEachUniqueAttendee(): void
    {
        $this->setupCommon([
            $this->makeAttendee('alice@example.com'),
            $this->makeAttendee('bob@example.com'),
        ]);

        $emailsSent = [];
        $pendingMail = Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('locale')->andReturnSelf();
        $pendingMail->shouldReceive('send')->with(Mockery::type(OccurrenceCancellationMail::class));

        $this->mailer->shouldReceive('to')->andReturnUsing(function ($email) use (&$emailsSent, $pendingMail) {
            $emailsSent[] = $email;
            return $pendingMail;
        });

        $job = new SendOccurrenceCancellationEmailJob(1, 10);
        $job->handle($this->eventRepository, $this->occurrenceRepository, $this->attendeeRepository, $this->mailer, $this->mailBuilderService);

        $this->assertCount(2, $emailsSent);
        $this->assertContains('alice@example.com', $emailsSent);
        $this->assertContains('bob@example.com', $emailsSent);
    }

    public function testHandleDeduplicatesByEmail(): void
    {
        $this->setupCommon([
            $this->makeAttendee('same@example.com'),
            $this->makeAttendee('same@example.com'),
        ]);

        $emailsSent = [];
        $pendingMail = Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('locale')->andReturnSelf();
        $pendingMail->shouldReceive('send');

        $this->mailer->shouldReceive('to')->andReturnUsing(function ($email) use (&$emailsSent, $pendingMail) {
            $emailsSent[] = $email;
            return $pendingMail;
        });

        $job = new SendOccurrenceCancellationEmailJob(1, 10);
        $job->handle($this->eventRepository, $this->occurrenceRepository, $this->attendeeRepository, $this->mailer, $this->mailBuilderService);

        $this->assertCount(1, $emailsSent);
    }

    public function testHandleReturnsEarlyWhenNoAttendees(): void
    {
        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 14:00:00');

        $this->occurrenceRepository->shouldReceive('findById')->once()->andReturn($occurrence);
        $this->eventRepository->shouldReceive('loadRelation')->twice()->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->once()->andReturn($this->makeEvent());
        $this->attendeeRepository->shouldReceive('findWhere')->once()->andReturn(collect());

        $this->mailer->shouldNotReceive('to');

        $job = new SendOccurrenceCancellationEmailJob(1, 10);
        $job->handle($this->eventRepository, $this->occurrenceRepository, $this->attendeeRepository, $this->mailer, $this->mailBuilderService);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
