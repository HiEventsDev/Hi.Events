<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\EventOccurrenceRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventOccurrenceRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private int $eventId;

    private int $runningOccurrenceId;

    private int $futureOccurrenceId;

    private int $endedOccurrenceId;

    private int $nullEndPastStartOccurrenceId;

    private int $nullEndFutureStartOccurrenceId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;
        $now = now()->toDateTimeString();

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Occurrences Organizer',
            'email' => 'occurrences-organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventId = DB::table('events')->insertGetId([
            'title' => 'Occurrence filter event',
            'status' => 'DRAFT',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->runningOccurrenceId = $this->createOccurrence(now()->subDay(), now()->addDay());
        $this->futureOccurrenceId = $this->createOccurrence(now()->addDays(2), now()->addDays(2)->addHours(2));
        $this->endedOccurrenceId = $this->createOccurrence(now()->subDays(3), now()->subDays(3)->addHours(2));
        $this->nullEndPastStartOccurrenceId = $this->createOccurrence(now()->subHours(3), null);
        $this->nullEndFutureStartOccurrenceId = $this->createOccurrence(now()->addHours(3), null);
    }

    public function test_upcoming_time_period_includes_running_occurrence(): void
    {
        $ids = $this->findOccurrenceIds('upcoming');

        $this->assertContains($this->runningOccurrenceId, $ids);
        $this->assertContains($this->futureOccurrenceId, $ids);
        $this->assertContains($this->nullEndFutureStartOccurrenceId, $ids);
        $this->assertNotContains($this->endedOccurrenceId, $ids);
        $this->assertNotContains($this->nullEndPastStartOccurrenceId, $ids);
    }

    public function test_past_time_period_excludes_running_occurrence(): void
    {
        $ids = $this->findOccurrenceIds('past');

        $this->assertContains($this->endedOccurrenceId, $ids);
        $this->assertContains($this->nullEndPastStartOccurrenceId, $ids);
        $this->assertNotContains($this->runningOccurrenceId, $ids);
        $this->assertNotContains($this->futureOccurrenceId, $ids);
        $this->assertNotContains($this->nullEndFutureStartOccurrenceId, $ids);
    }

    private function findOccurrenceIds(string $timePeriod): array
    {
        $params = QueryParamsDTO::fromArray([
            'filter_fields' => ['time_period' => ['eq' => $timePeriod]],
            'per_page' => 100,
        ]);

        $result = $this->app->make(EventOccurrenceRepository::class)->findByEventId($this->eventId, $params);

        return collect($result->items())
            ->map(fn (EventOccurrenceDomainObject $occurrence) => $occurrence->getId())
            ->all();
    }

    private function createOccurrence($startDate, $endDate): int
    {
        $now = now()->toDateTimeString();

        return DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $this->eventId,
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
