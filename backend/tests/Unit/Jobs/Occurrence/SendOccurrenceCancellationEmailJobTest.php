<?php

namespace Tests\Unit\Jobs\Occurrence;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Jobs\Occurrence\SendOccurrenceCancellationEmailJob;
use HiEvents\Mail\Occurrence\OccurrenceCancellationMail;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Email\MailBuilderService;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Bus;
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

    private function setupCommon(array $attendeeIds, array $attendees): void
    {
        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $occurrence->shouldReceive('getStartDate')->andReturn('2026-06-15 14:00:00');

        $this->occurrenceRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->occurrenceRepository->shouldReceive('findById')->with(10)->once()->andReturn($occurrence);
        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->with(1)->once()->andReturn($this->makeEvent());
        $this->attendeeRepository
            ->shouldReceive('findWhereIn')
            ->once()
            ->with('id', $attendeeIds)
            ->andReturn(collect($attendees));
    }

    public function test_handle_sends_email_only_to_provided_attendees(): void
    {
        $this->setupCommon([101, 102], [
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

        $job = new SendOccurrenceCancellationEmailJob(1, 10, [101, 102]);
        $job->handle($this->eventRepository, $this->occurrenceRepository, $this->attendeeRepository, $this->mailer, $this->mailBuilderService);

        $this->assertCount(2, $emailsSent);
        $this->assertContains('alice@example.com', $emailsSent);
        $this->assertContains('bob@example.com', $emailsSent);
    }

    public function test_handle_deduplicates_by_email(): void
    {
        $this->setupCommon([101, 102], [
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

        $job = new SendOccurrenceCancellationEmailJob(1, 10, [101, 102]);
        $job->handle($this->eventRepository, $this->occurrenceRepository, $this->attendeeRepository, $this->mailer, $this->mailBuilderService);

        $this->assertCount(1, $emailsSent);
    }

    public function test_handle_returns_early_when_no_attendee_ids(): void
    {
        $this->occurrenceRepository->shouldNotReceive('findById');
        $this->eventRepository->shouldNotReceive('findById');
        $this->attendeeRepository->shouldNotReceive('findWhereIn');
        $this->mailer->shouldNotReceive('to');

        $job = new SendOccurrenceCancellationEmailJob(1, 10, []);
        $job->handle($this->eventRepository, $this->occurrenceRepository, $this->attendeeRepository, $this->mailer, $this->mailBuilderService);

        $this->assertTrue(true);
    }

    public function test_dispatch_chunked_splits_attendee_ids_into_jobs_of_at_most_one_thousand(): void
    {
        Bus::fake();

        SendOccurrenceCancellationEmailJob::dispatchChunked(1, 10, range(1, 2500), true);

        Bus::assertDispatchedTimes(SendOccurrenceCancellationEmailJob::class, 3);

        $dispatchedIds = [];
        Bus::assertDispatched(SendOccurrenceCancellationEmailJob::class, function (SendOccurrenceCancellationEmailJob $job) use (&$dispatchedIds) {
            $dispatchedIds[] = $job->attendeeIds;

            return $job->eventId === 1
                && $job->occurrenceId === 10
                && $job->refundOrders === true
                && count($job->attendeeIds) <= 1000;
        });

        $this->assertSame(range(1, 2500), array_merge(...$dispatchedIds));
    }

    public function test_dispatch_chunked_dispatches_nothing_when_no_attendee_ids(): void
    {
        Bus::fake();

        SendOccurrenceCancellationEmailJob::dispatchChunked(1, 10, [], false);

        Bus::assertNotDispatched(SendOccurrenceCancellationEmailJob::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
