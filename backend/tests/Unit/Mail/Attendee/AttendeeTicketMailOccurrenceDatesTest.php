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

class AttendeeTicketMailOccurrenceDatesTest extends TestCase
{
    public function test_an_occurrence_without_an_end_shows_only_its_own_date(): void
    {
        $firstDate = $this->occurrence(1, '2026-11-02 19:00:00', null);
        $mail = $this->mail($firstDate, collect([
            $firstDate,
            $this->occurrence(2, '2026-11-09 19:00:00', null),
            $this->occurrence(3, '2026-11-16 19:00:00', null),
        ]));

        $html = $mail->render();
        $ics = $this->icsFor($mail);

        $this->assertStringContainsString('Mon, Nov 2, 2026 · 7:00 PM<br>', $html);
        $this->assertStringNotContainsString('Nov 9, 2026', $html);
        $this->assertStringNotContainsString('Nov 16, 2026', $html);
        $this->assertStringContainsString('DTSTART:20261102T190000', $ics);
        $this->assertStringNotContainsString('DTEND', $ics);
        $this->assertStringNotContainsString('20261109', $ics);
        $this->assertStringNotContainsString('20261116', $ics);
    }

    public function test_an_occurrence_with_an_end_shows_its_own_range(): void
    {
        $firstDate = $this->occurrence(1, '2026-11-02 19:00:00', '2026-11-02 21:00:00');
        $mail = $this->mail($firstDate, collect([
            $firstDate,
            $this->occurrence(2, '2026-11-16 19:00:00', '2026-11-16 21:00:00'),
        ]));

        $html = $mail->render();
        $ics = $this->icsFor($mail);

        $this->assertStringContainsString('Mon, Nov 2, 2026 · 7:00 PM – 9:00 PM', $html);
        $this->assertStringNotContainsString('Nov 16, 2026', $html);
        $this->assertStringContainsString('DTEND:20261102T210000', $ics);
        $this->assertStringNotContainsString('20261116', $ics);
    }

    public function test_an_end_equal_to_the_start_is_not_shown_as_a_range(): void
    {
        $onlyDate = $this->occurrence(1, '2026-11-02 19:00:00', '2026-11-02 19:00:00');
        $mail = $this->mail($onlyDate, collect([$onlyDate]));

        $html = $mail->render();

        $this->assertStringContainsString('Mon, Nov 2, 2026 · 7:00 PM<br>', $html);
        $this->assertStringNotContainsString('7:00 PM – 7:00 PM', $html);
        $this->assertStringNotContainsString('DTEND', $this->icsFor($mail));
    }

    public function test_without_an_occurrence_a_single_date_event_without_an_end_shows_no_range(): void
    {
        $onlyDate = $this->occurrence(1, '2026-11-02 19:00:00', null);
        $attendee = $this->attendee(null);

        $mail = new AttendeeTicketMail(
            order: $this->order(),
            attendee: $attendee,
            event: $this->event(collect([$onlyDate])),
            eventSettings: (new EventSettingDomainObject)->setSupportEmail('support@example.com'),
            organizer: $this->organizer(),
        );

        $html = $mail->render();

        $this->assertStringContainsString('Mon, Nov 2, 2026 · 7:00 PM<br>', $html);
        $this->assertStringNotContainsString('7:00 PM – 7:00 PM', $html);
        $this->assertStringNotContainsString('DTEND', $this->icsFor($mail));
    }

    /**
     * @param  Collection<int, EventOccurrenceDomainObject>  $series
     */
    private function mail(EventOccurrenceDomainObject $occurrence, Collection $series): AttendeeTicketMail
    {
        return new AttendeeTicketMail(
            order: $this->order(),
            attendee: $this->attendee($occurrence),
            event: $this->event($series),
            eventSettings: (new EventSettingDomainObject)->setSupportEmail('support@example.com'),
            organizer: $this->organizer(),
            occurrence: $occurrence,
        );
    }

    private function occurrence(int $id, string $start, ?string $end): EventOccurrenceDomainObject
    {
        return (new EventOccurrenceDomainObject)
            ->setId($id)
            ->setStartDate($start)
            ->setEndDate($end);
    }

    /**
     * @param  Collection<int, EventOccurrenceDomainObject>  $series
     */
    private function event(Collection $series): EventDomainObject
    {
        return (new EventDomainObject)
            ->setId(10)
            ->setAccountId(1)
            ->setTitle('Weekly Quiz')
            ->setTimezone('UTC')
            ->setEventOccurrences($series);
    }

    private function order(): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(30)
            ->setShortId('o_short')
            ->setStatus(OrderStatus::COMPLETED->name);
    }

    private function organizer(): OrganizerDomainObject
    {
        return (new OrganizerDomainObject)
            ->setId(1)
            ->setName('Organizer')
            ->setEmail('organizer@example.com');
    }

    private function attendee(?EventOccurrenceDomainObject $occurrence): AttendeeDomainObject
    {
        return (new AttendeeDomainObject)
            ->setId(101)
            ->setShortId('a_first')
            ->setFirstName('Sam')
            ->setLastName('Buyer')
            ->setEmail('buyer@example.com')
            ->setEventOccurrenceId($occurrence?->getId())
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
