<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\EventRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private int $accountId;

    private int $organizerId;

    private int $userId;

    private int $eventWithoutOccurrencesId;

    private int $eventWithFutureOccurrenceId;

    private int $eventWithPastOccurrenceId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;

        $now = now()->toDateTimeString();

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Events Organizer',
            'email' => 'events-organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventWithoutOccurrencesId = $this->createEvent('Event without occurrences');
        $this->eventWithFutureOccurrenceId = $this->createEvent('Event with future occurrence');
        $this->eventWithPastOccurrenceId = $this->createEvent('Event with past occurrence');

        $this->createOccurrence($this->eventWithFutureOccurrenceId, now()->addDay(), now()->addDay()->addHours(2));
        $this->createOccurrence($this->eventWithPastOccurrenceId, now()->subDays(2), now()->subDays(2)->addHours(2));
    }

    public function test_upcoming_filter_includes_events_with_no_occurrences(): void
    {
        $ids = $this->findEventIds('upcoming');

        $this->assertContains($this->eventWithoutOccurrencesId, $ids);
        $this->assertContains($this->eventWithFutureOccurrenceId, $ids);
        $this->assertNotContains($this->eventWithPastOccurrenceId, $ids);
    }

    public function test_ended_filter_only_includes_events_whose_occurrences_have_all_passed(): void
    {
        $ids = $this->findEventIds('ended');

        $this->assertContains($this->eventWithPastOccurrenceId, $ids);
        $this->assertNotContains($this->eventWithoutOccurrencesId, $ids);
        $this->assertNotContains($this->eventWithFutureOccurrenceId, $ids);
    }

    public function test_running_multi_day_occurrence_keeps_event_upcoming(): void
    {
        $eventId = $this->createEvent('Running multi-day '.uniqid());
        $this->createOccurrence($eventId, now()->subDay(), now()->addDay());

        $this->assertContains($eventId, $this->findEventIds('upcoming'));
        $this->assertNotContains($eventId, $this->findEventIds('ended'));
    }

    public function test_null_end_occurrence_with_past_start_marks_event_ended(): void
    {
        $eventId = $this->createEvent('Null end past start '.uniqid());
        $this->createOccurrence($eventId, now()->subDay(), null);

        $this->assertContains($eventId, $this->findEventIds('ended'));
        $this->assertNotContains($eventId, $this->findEventIds('upcoming'));
    }

    public function test_null_end_occurrence_with_future_start_keeps_event_upcoming(): void
    {
        $eventId = $this->createEvent('Null end future start '.uniqid());
        $this->createOccurrence($eventId, now()->addDay(), null);

        $this->assertContains($eventId, $this->findEventIds('upcoming'));
        $this->assertNotContains($eventId, $this->findEventIds('ended'));
    }

    public function test_get_all_events_for_admin_hydrates_occurrence_dates(): void
    {
        $title = 'Admin hydration event '.uniqid();
        $eventId = $this->createEvent($title, status: 'LIVE');
        $this->createOccurrence($eventId, now()->addHours(2), now()->addHours(4));

        $result = $this->app->make(EventRepository::class)->getAllEventsForAdmin(search: $title);

        $events = collect($result->items());
        $this->assertCount(1, $events);

        /** @var EventDomainObject $event */
        $event = $events->first();
        $this->assertSame($eventId, $event->getId());
        $this->assertNotNull($event->getStartDate(), 'Admin list must hydrate occurrences so start_date resolves');
        $this->assertNotNull($event->getOrganizer());
        $this->assertNotNull($event->getAccount());
    }

    public function test_get_upcoming_events_for_admin_hydrates_occurrence_dates(): void
    {
        $eventId = $this->createEvent('Upcoming admin event '.uniqid(), status: 'LIVE');
        $this->createOccurrence($eventId, now()->addHours(2), now()->addHours(4));

        $result = $this->app->make(EventRepository::class)->getUpcomingEventsForAdmin(perPage: 100);

        /** @var EventDomainObject|null $event */
        $event = collect($result->items())->first(fn (EventDomainObject $e) => $e->getId() === $eventId);

        $this->assertNotNull($event, 'LIVE event with an occurrence in the next 24h should appear in the upcoming admin list');
        $this->assertNotNull($event->getStartDate(), 'Upcoming admin list must hydrate occurrences so start_date resolves');
        $this->assertNotNull($event->getOrganizer());
        $this->assertNotNull($event->getAccount());
    }

    public function test_get_all_events_for_admin_sorts_by_earliest_occurrence_start_date(): void
    {
        $earlyId = $this->createEvent('Sort early '.uniqid());
        $lateId = $this->createEvent('Sort late '.uniqid());
        $this->createOccurrence($earlyId, now()->addDays(3), now()->addDays(3)->addHours(2));
        $this->createOccurrence($lateId, now()->addDays(30), now()->addDays(30)->addHours(2));

        $result = $this->app->make(EventRepository::class)
            ->getAllEventsForAdmin(perPage: 100, sortBy: 'start_date', sortDirection: 'asc');

        $ids = collect($result->items())->map(fn (EventDomainObject $e) => $e->getId())->all();

        $this->assertLessThan(array_search($lateId, $ids, true), array_search($earlyId, $ids, true));
        $this->assertLessThan(array_search($this->eventWithoutOccurrencesId, $ids, true), array_search($lateId, $ids, true));
    }

    public function test_start_date_sort_orders_events_chronologically_not_by_creation_order(): void
    {
        $earlierStartId = $this->createEvent('Chrono earlier '.uniqid(), createdAt: now()->subDays(2));
        $laterStartId = $this->createEvent('Chrono later '.uniqid(), createdAt: now()->subDay());
        $this->createOccurrence($earlierStartId, now()->addDays(5), now()->addDays(5)->addHours(2));
        $this->createOccurrence($laterStartId, now()->addDays(10), now()->addDays(10)->addHours(2));

        $ids = $this->findEventIds('upcoming', sortBy: 'start_date', sortDirection: 'asc');

        $this->assertLessThan(array_search($laterStartId, $ids, true), array_search($earlierStartId, $ids, true));
    }

    public function test_upcoming_start_date_sort_uses_next_upcoming_occurrence(): void
    {
        $partiallyElapsedId = $this->createEvent('Partially elapsed '.uniqid());
        $singleUpcomingId = $this->createEvent('Single upcoming '.uniqid());
        $this->createOccurrence($partiallyElapsedId, now()->subDays(30), now()->subDays(30)->addHours(2));
        $this->createOccurrence($partiallyElapsedId, now()->addDays(10), now()->addDays(10)->addHours(2));
        $this->createOccurrence($singleUpcomingId, now()->addDays(5), now()->addDays(5)->addHours(2));

        $ids = $this->findEventIds('upcoming', sortBy: 'start_date', sortDirection: 'asc');

        $this->assertLessThan(array_search($partiallyElapsedId, $ids, true), array_search($singleUpcomingId, $ids, true));
    }

    public function test_upcoming_start_date_sort_puts_events_without_occurrences_last(): void
    {
        $withOccurrenceId = $this->createEvent('Has occurrence '.uniqid());
        $this->createOccurrence($withOccurrenceId, now()->addDays(60), now()->addDays(60)->addHours(2));

        $ids = $this->findEventIds('upcoming', sortBy: 'start_date', sortDirection: 'asc');

        $this->assertLessThan(array_search($this->eventWithoutOccurrencesId, $ids, true), array_search($withOccurrenceId, $ids, true));
    }

    public function test_ended_start_date_sort_desc_puts_most_recent_past_event_first(): void
    {
        $olderPastId = $this->createEvent('Older past '.uniqid(), createdAt: now()->subDay());
        $recentPastId = $this->createEvent('Recent past '.uniqid(), createdAt: now()->subDays(2));
        $this->createOccurrence($olderPastId, now()->subDays(20), now()->subDays(20)->addHours(2));
        $this->createOccurrence($recentPastId, now()->subDays(40), now()->subDays(40)->addHours(2));
        $this->createOccurrence($recentPastId, now()->subDays(5), now()->subDays(5)->addHours(2));

        $ids = $this->findEventIds('ended', sortBy: 'start_date', sortDirection: 'desc');

        $this->assertLessThan(array_search($olderPastId, $ids, true), array_search($recentPastId, $ids, true));
    }

    public function test_unknown_sort_column_falls_back_to_created_at_desc(): void
    {
        $olderId = $this->createEvent('Fallback older '.uniqid(), createdAt: now()->subDays(3));
        $newerId = $this->createEvent('Fallback newer '.uniqid(), createdAt: now()->subDay());

        $ids = $this->findEventIds('upcoming', sortBy: 'not_a_column', sortDirection: 'desc');

        $this->assertLessThan(array_search($olderId, $ids, true), array_search($newerId, $ids, true));
    }

    private function findEventIds(string $eventsStatus, string $sortBy = 'created_at', string $sortDirection = 'desc'): array
    {
        $params = QueryParamsDTO::fromArray([
            'eventsStatus' => $eventsStatus,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
            'per_page' => 100,
        ]);

        $result = $this->app->make(EventRepository::class)->findEvents(
            where: [
                'account_id' => $this->accountId,
                'organizer_id' => $this->organizerId,
            ],
            params: $params,
        );

        return collect($result->items())
            ->map(fn (EventDomainObject $event) => $event->getId())
            ->all();
    }

    private function createEvent(string $title, string $status = 'DRAFT', $createdAt = null): int
    {
        $createdAt = ($createdAt ?? now())->toDateTimeString();

        return DB::table('events')->insertGetId([
            'title' => $title,
            'status' => $status,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createOccurrence(int $eventId, $startDate, $endDate): void
    {
        $now = now()->toDateTimeString();

        DB::table('event_occurrences')->insert([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate?->toDateTimeString(),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
