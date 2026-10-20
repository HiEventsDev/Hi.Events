<?php

namespace Tests\Unit\Mail\Attendee;

use Closure;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Attendee\AttendeeTicketMail;
use ReflectionClass;
use Tests\TestCase;

class AttendeeTicketMailTest extends TestCase
{
    public function test_attachments_render_for_a_mail_queued_by_a_previous_release(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setTitle('Test Event')
            ->setTimezone('UTC')
            ->setEventOccurrences(collect([
                (new EventOccurrenceDomainObject)
                    ->setStartDate('2026-09-01 18:00:00')
                    ->setEndDate('2026-09-01 20:00:00'),
            ]));

        $attendee = (new AttendeeDomainObject)->setId(5);

        $organizer = (new OrganizerDomainObject)
            ->setEmail('organizer@example.com')
            ->setName('Organizer');

        $mail = (new ReflectionClass(AttendeeTicketMail::class))->newInstanceWithoutConstructor();
        Closure::bind(function () use ($event, $attendee, $organizer): void {
            $this->event = $event;
            $this->attendee = $attendee;
            $this->organizer = $organizer;
        }, $mail, AttendeeTicketMail::class)();

        $attachments = $mail->attachments();

        $this->assertCount(1, $attachments);
    }

    public function test_content_renders_one_ticket_for_a_mail_queued_before_grouping(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setTitle('Test Event')
            ->setTimezone('UTC')
            ->setEventOccurrences(collect());

        $attendee = (new AttendeeDomainObject)->setId(5)->setShortId('a_old');
        $occurrence = (new EventOccurrenceDomainObject)
            ->setId(3)
            ->setStartDate('2026-09-01 18:00:00');

        $mail = (new ReflectionClass(AttendeeTicketMail::class))->newInstanceWithoutConstructor();
        Closure::bind(function () use ($event, $attendee, $occurrence): void {
            $this->event = $event;
            $this->attendee = $attendee;
            $this->occurrence = $occurrence;
            $this->order = new OrderDomainObject;
            $this->organizer = new OrganizerDomainObject;
            $this->eventSettings = new EventSettingDomainObject;
            $this->renderedTemplate = null;
        }, $mail, AttendeeTicketMail::class)();

        $tickets = $mail->content()->with['tickets'];

        $this->assertSame('🎟️ Your Ticket for Test Event', $mail->envelope()->subject);
        $this->assertCount(1, $tickets);
        $this->assertSame($attendee, $tickets[0]['attendee']);
        $this->assertSame($occurrence, $tickets[0]['occurrence']);
    }
}
