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

    private function findEventIds(string $eventsStatus): array
    {
        $params = QueryParamsDTO::fromArray([
            'eventsStatus' => $eventsStatus,
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
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

    private function createEvent(string $title): int
    {
        $now = now()->toDateTimeString();

        return DB::table('events')->insertGetId([
            'title' => $title,
            'status' => 'DRAFT',
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createOccurrence(int $eventId, $startDate, $endDate): void
    {
        $now = now()->toDateTimeString();

        DB::table('event_occurrences')->insert([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
