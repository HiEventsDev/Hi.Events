<?php

namespace Tests\Unit\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Status\EventLifecycleStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EventDomainObjectTest extends TestCase
{
    private function createOccurrence(
        string $startDate,
        ?string $endDate = null,
        string $status = 'ACTIVE',
    ): EventOccurrenceDomainObject {
        $occurrence = new EventOccurrenceDomainObject();
        $occurrence->setStartDate($startDate);
        $occurrence->setEndDate($endDate);
        $occurrence->setStatus($status);

        return $occurrence;
    }

    private function createEvent(?Collection $occurrences = null, ?string $timezone = null): EventDomainObject
    {
        $event = new EventDomainObject();

        if ($occurrences !== null) {
            $event->setEventOccurrences($occurrences);
        }

        if ($timezone !== null) {
            $event->setTimezone($timezone);
        }

        return $event;
    }

    public function testGetStartDateReturnsEarliestOccurrenceStartDate(): void
    {
        $earlier = Carbon::now()->subDays(3)->toDateTimeString();
        $later = Carbon::now()->subDay()->toDateTimeString();

        $occurrences = collect([
            $this->createOccurrence($later),
            $this->createOccurrence($earlier),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals($earlier, $event->getStartDate());
    }

    public function testGetStartDateReturnsNullWhenNoOccurrences(): void
    {
        $event = $this->createEvent();
        $this->assertNull($event->getStartDate());

        $eventWithEmpty = $this->createEvent(collect([]));
        $this->assertNull($eventWithEmpty->getStartDate());
    }

    public function testGetEndDateReturnsLatestOccurrenceEndDate(): void
    {
        $earlierEnd = Carbon::now()->addDay()->toDateTimeString();
        $laterEnd = Carbon::now()->addDays(3)->toDateTimeString();

        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subDay()->toDateTimeString(),
                $earlierEnd,
            ),
            $this->createOccurrence(
                Carbon::now()->toDateTimeString(),
                $laterEnd,
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals($laterEnd, $event->getEndDate());
    }

    public function testGetEndDateFallsBackToLatestStartDateWhenNoEndDates(): void
    {
        $earlierStart = Carbon::now()->subDay()->toDateTimeString();
        $laterStart = Carbon::now()->addDay()->toDateTimeString();

        $occurrences = collect([
            $this->createOccurrence($earlierStart),
            $this->createOccurrence($laterStart),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals($laterStart, $event->getEndDate());
    }

    public function testGetEndDateReturnsNullWhenNoOccurrences(): void
    {
        $event = $this->createEvent();
        $this->assertNull($event->getEndDate());

        $eventWithEmpty = $this->createEvent(collect([]));
        $this->assertNull($eventWithEmpty->getEndDate());
    }

    public function testIsEventInPastReturnsTrueWhenAllOccurrencesArePast(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subDays(3)->toDateTimeString(),
                Carbon::now()->subDays(2)->toDateTimeString(),
            ),
            $this->createOccurrence(
                Carbon::now()->subDays(2)->toDateTimeString(),
                Carbon::now()->subDay()->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertTrue($event->isEventInPast());
    }

    public function testIsEventInPastReturnsFalseWhenSomeOccurrencesAreFuture(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subDays(2)->toDateTimeString(),
                Carbon::now()->subDay()->toDateTimeString(),
            ),
            $this->createOccurrence(
                Carbon::now()->addDay()->toDateTimeString(),
                Carbon::now()->addDays(2)->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertFalse($event->isEventInPast());
    }

    public function testIsEventInFutureReturnsTrueWhenEarliestStartIsFuture(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->addDay()->toDateTimeString(),
                Carbon::now()->addDays(2)->toDateTimeString(),
            ),
            $this->createOccurrence(
                Carbon::now()->addDays(3)->toDateTimeString(),
                Carbon::now()->addDays(4)->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertTrue($event->isEventInFuture());
    }

    public function testIsEventInFutureReturnsFalseWhenEarliestStartIsPast(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subDay()->toDateTimeString(),
                Carbon::now()->addDay()->toDateTimeString(),
            ),
            $this->createOccurrence(
                Carbon::now()->addDays(2)->toDateTimeString(),
                Carbon::now()->addDays(3)->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertFalse($event->isEventInFuture());
    }

    public function testIsEventOngoingReturnsTrueWhenActiveOccurrenceHasStartedButNotEnded(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subHour()->toDateTimeString(),
                Carbon::now()->addHour()->toDateTimeString(),
                EventOccurrenceStatus::ACTIVE->name,
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertTrue($event->isEventOngoing());
    }

    public function testIsEventOngoingReturnsFalseForCancelledOccurrences(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subHour()->toDateTimeString(),
                Carbon::now()->addHour()->toDateTimeString(),
                EventOccurrenceStatus::CANCELLED->name,
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertFalse($event->isEventOngoing());
    }

    public function testIsEventOngoingReturnsTrueWhenActiveOccurrenceHasNoEndDate(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subHour()->toDateTimeString(),
                null,
                EventOccurrenceStatus::ACTIVE->name,
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertTrue($event->isEventOngoing());
    }

    public function testIsEventOngoingReturnsFalseWhenNoOccurrences(): void
    {
        $event = $this->createEvent();
        $this->assertFalse($event->isEventOngoing());

        $eventWithEmpty = $this->createEvent(collect([]));
        $this->assertFalse($eventWithEmpty->isEventOngoing());
    }

    public function testGetLifecycleStatusReturnsOngoingWhenOngoing(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subHour()->toDateTimeString(),
                Carbon::now()->addHour()->toDateTimeString(),
                EventOccurrenceStatus::ACTIVE->name,
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals(EventLifecycleStatus::ONGOING->name, $event->getLifecycleStatus());
    }

    public function testGetLifecycleStatusReturnsUpcomingWhenAllFuture(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->addDay()->toDateTimeString(),
                Carbon::now()->addDays(2)->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals(EventLifecycleStatus::UPCOMING->name, $event->getLifecycleStatus());
    }

    public function testGetLifecycleStatusReturnsEndedWhenAllPast(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subDays(3)->toDateTimeString(),
                Carbon::now()->subDay()->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals(EventLifecycleStatus::ENDED->name, $event->getLifecycleStatus());
    }

    public function testIsRecurringReturnsTrueForRecurringType(): void
    {
        $event = new EventDomainObject();
        $event->setType(EventType::RECURRING->name);

        $this->assertTrue($event->isRecurring());
    }

    public function testIsRecurringReturnsFalseForSingleType(): void
    {
        $event = new EventDomainObject();
        $event->setType(EventType::SINGLE->name);

        $this->assertFalse($event->isRecurring());
    }
}
