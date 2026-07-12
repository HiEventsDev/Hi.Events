<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Event;

use HiEvents\Models\User;
use HiEvents\Services\Domain\Event\DuplicateEventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DuplicateEventServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_duplicating_excludes_occurrences_that_are_past_even_on_the_same_utc_day(): void
    {
        $user = User::factory()->withAccount()->create();
        $this->actingAs($user);
        $accountId = $user->accounts()->first()->id;
        $now = now()->toDateTimeString();

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Duplicate Organizer',
            'email' => 'duplicate-organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('organizer_settings')->insert([
            'organizer_id' => $organizerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $eventId = DB::table('events')->insertGetId([
            'title' => 'Recurring source event',
            'status' => 'DRAFT',
            'type' => 'RECURRING',
            'recurrence_rule' => json_encode([
                'range' => ['type' => 'count', 'count' => 2, 'start' => now()->utc()->toDateString()],
                'interval' => 1,
                'frequency' => 'weekly',
                'days_of_week' => ['monday'],
                'times_of_day' => ['19:00'],
            ]),
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // A past occurrence on the same UTC day as the duplication.
        $pastTodayStart = now()->utc()->startOfDay()->addSecond();
        $futureStart = now()->utc()->addDay();

        $this->insertOccurrence($eventId, $pastTodayStart->toDateTimeString());
        $this->insertOccurrence($eventId, $futureStart->toDateTimeString());

        $newEvent = $this->app->make(DuplicateEventService::class)->duplicateEvent(
            eventId: (string) $eventId,
            accountId: (string) $accountId,
            title: 'Duplicated event',
            startDate: now()->utc()->addDays(10)->toDateTimeString(),
            duplicateProducts: false,
            duplicateQuestions: false,
            duplicateSettings: false,
            duplicatePromoCodes: false,
            duplicateCapacityAssignments: false,
            duplicateCheckInLists: false,
            duplicateEventCoverImage: false,
            duplicateTicketLogo: false,
            duplicateWebhooks: false,
            duplicateAffiliates: false,
        );

        $clonedStartDates = DB::table('event_occurrences')
            ->where('event_id', $newEvent->getId())
            ->whereNull('deleted_at')
            ->pluck('start_date')
            ->map(fn ($date) => (string) $date)
            ->all();

        $this->assertContains($futureStart->toDateTimeString(), $clonedStartDates, 'The future occurrence must be cloned');
        $this->assertNotContains($pastTodayStart->toDateTimeString(), $clonedStartDates, 'A same-UTC-day but already-past occurrence must not be cloned');
    }

    private function insertOccurrence(int $eventId, string $startDate): void
    {
        $now = now()->toDateTimeString();

        DB::table('event_occurrences')->insert([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => $startDate,
            'end_date' => null,
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
