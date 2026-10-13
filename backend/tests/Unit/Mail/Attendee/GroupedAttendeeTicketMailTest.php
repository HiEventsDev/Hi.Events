<?php

namespace Tests\Unit\Mail\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Attendee\AttendeeTicketMail;
use Illuminate\Support\Collection;
use Tests\TestCase;

class GroupedAttendeeTicketMailTest extends TestCase
{
    private EventDomainObject $event;

    private OrganizerDomainObject $organizer;

    private EventSettingDomainObject $settings;

    private OrderDomainObject $order;

    private EventOccurrenceDomainObject $firstDate;

    private EventOccurrenceDomainObject $secondDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firstDate = (new EventOccurrenceDomainObject)
            ->setId(1)
            ->setStartDate('2026-11-05 18:00:00')
            ->setEndDate('2026-11-05 20:00:00');
        $this->secondDate = (new EventOccurrenceDomainObject)
            ->setId(2)
            ->setStartDate('2026-11-12 18:00:00')
            ->setEndDate('2026-11-12 20:00:00')
            ->setLabel('Late show');

        $this->organizer = (new OrganizerDomainObject)
            ->setId(1)
            ->setName('Organizer')
            ->setEmail('organizer@example.com');

        $this->settings = (new EventSettingDomainObject)
            ->setSupportEmail('support@example.com');

        $this->event = (new EventDomainObject)
            ->setId(10)
            ->setAccountId(1)
            ->setTitle('Jazz Nights')
            ->setTimezone('UTC')
            ->setEventOccurrences(collect([$this->firstDate, $this->secondDate]));

        $this->order = (new OrderDomainObject)
            ->setId(30)
            ->setShortId('o_short')
            ->setStatus(OrderStatus::COMPLETED->name);
    }

    public function test_grouped_mail_lists_every_ticket_with_its_own_date_and_link(): void
    {
        $attendees = collect([
            $this->attendee(101, 'a_first', $this->firstDate),
            $this->attendee(102, 'a_second', $this->secondDate),
        ]);

        $html = $this->mail($attendees)->render();

        $this->assertStringContainsString('Thu, Nov 5, 2026', $html);
        $this->assertStringContainsString('Thu, Nov 12, 2026', $html);
        $this->assertStringContainsString('Late show', $html);
        $this->assertStringContainsString('/product/10/a_first', $html);
        $this->assertStringContainsString('/product/10/a_second', $html);
        $this->assertSame(2, substr_count($html, 'General Admission'));
    }

    public function test_grouped_mail_uses_plural_subject(): void
    {
        $mail = $this->mail(collect([
            $this->attendee(101, 'a_first', $this->firstDate),
            $this->attendee(102, 'a_second', $this->firstDate),
        ]));

        $this->assertSame('🎟️ Your Tickets for Jazz Nights', $mail->envelope()->subject);
    }

    public function test_single_ticket_mail_keeps_singular_subject_and_one_link(): void
    {
        $attendee = $this->attendee(101, 'a_first', $this->firstDate);
        $mail = new AttendeeTicketMail(
            order: $this->order,
            attendee: $attendee,
            event: $this->event,
            eventSettings: $this->settings,
            organizer: $this->organizer,
            occurrence: $this->firstDate,
        );

        $this->assertSame('🎟️ Your Ticket for Jazz Nights', $mail->envelope()->subject);
        $this->assertSame(1, substr_count($mail->render(), '/product/10/a_first'));
    }

    public function test_calendar_has_one_entry_per_distinct_date(): void
    {
        $mail = $this->mail(collect([
            $this->attendee(101, 'a_first', $this->firstDate),
            $this->attendee(102, 'a_second', $this->firstDate),
            $this->attendee(103, 'a_third', $this->secondDate),
        ]));

        $ics = $this->icsFor($mail);

        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('UID:event-101', $ics);
        $this->assertStringContainsString('UID:event-103', $ics);
        $this->assertStringNotContainsString('UID:event-102', $ics);
        $this->assertStringContainsString('20261112T180000', $ics);
    }

    private function mail(Collection $attendees): AttendeeTicketMail
    {
        return new AttendeeTicketMail(
            order: $this->order,
            attendee: $attendees->first(),
            event: $this->event,
            eventSettings: $this->settings,
            organizer: $this->organizer,
            occurrence: $attendees->first()->getEventOccurrence(),
            attendees: $attendees,
        );
    }

    private function attendee(int $id, string $shortId, EventOccurrenceDomainObject $occurrence): AttendeeDomainObject
    {
        return (new AttendeeDomainObject)
            ->setId($id)
            ->setShortId($shortId)
            ->setFirstName('Sam')
            ->setLastName('Buyer')
            ->setEmail('buyer@example.com')
            ->setEventOccurrenceId($occurrence->getId())
            ->setEventOccurrence($occurrence)
            ->setProduct((new ProductDomainObject)->setTitle('General Admission'));
    }

    private function icsFor(AttendeeTicketMail $mail): string
    {
        $attachment = $mail->attachments()[0];

        $data = null;
        $attachment->attachWith(
            fn () => null,
            function ($resolver) use (&$data) {
                $data = $resolver();
            },
        );

        return $data;
    }
}
