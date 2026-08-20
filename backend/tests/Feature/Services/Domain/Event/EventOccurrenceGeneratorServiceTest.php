<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\EventOccurrenceGeneratorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventOccurrenceGeneratorServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventOccurrenceGeneratorService $generator;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(EventOccurrenceGeneratorService::class);

        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;
        $now = now()->toDateTimeString();

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Generator Organizer',
            'email' => 'generator@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventId = DB::table('events')->insertGetId([
            'title' => 'Generator Event',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function weeklyRule(int $count = 3): array
    {
        return [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['monday'],
            'range' => ['type' => 'count', 'count' => $count, 'start' => '2030-06-03'],
            'times_of_day' => ['19:00'],
            'duration_minutes' => 120,
        ];
    }

    private function event(): EventDomainObject
    {
        return $this->app
            ->make(EventRepositoryInterface::class)
            ->findById($this->eventId);
    }

    private function liveOccurrences(): array
    {
        return DB::table('event_occurrences')
            ->where('event_id', $this->eventId)
            ->whereNull('deleted_at')
            ->orderBy('start_date')
            ->get()
            ->all();
    }

    public function test_regenerating_the_same_rule_is_idempotent(): void
    {
        $this->generator->generate($this->event(), $this->weeklyRule());
        $firstRun = $this->liveOccurrences();
        $this->assertCount(3, $firstRun);

        $this->generator->generate($this->event(), $this->weeklyRule());
        $secondRun = $this->liveOccurrences();

        $this->assertCount(3, $secondRun, 'Re-running an identical rule must not create new occurrences');
        $this->assertSame(
            array_column($firstRun, 'id'),
            array_column($secondRun, 'id'),
            'Re-running an identical rule must keep the same occurrence ids'
        );
        $this->assertSame(
            0,
            DB::table('event_occurrences')->where('event_id', $this->eventId)->whereNotNull('deleted_at')->count(),
            'Re-running an identical rule must not soft-delete any occurrence'
        );
    }

    public function test_generated_occurrences_have_unique_short_ids_and_expected_columns(): void
    {
        $this->generator->generate($this->event(), $this->weeklyRule(count: 3));
        $occurrences = $this->liveOccurrences();

        $this->assertCount(3, $occurrences);

        $shortIds = array_column($occurrences, 'short_id');
        $this->assertCount(3, array_unique($shortIds));

        foreach ($occurrences as $occurrence) {
            $this->assertStringStartsWith('oc_', $occurrence->short_id);
            $this->assertSame('ACTIVE', $occurrence->status);
            $this->assertSame(0, $occurrence->used_capacity);
            $this->assertFalse((bool) $occurrence->is_overridden);
            $this->assertNotNull($occurrence->end_date);
            $this->assertNotNull($occurrence->created_at);
        }
    }

    public function test_regenerating_the_same_rule_issues_no_occurrence_updates(): void
    {
        $this->generator->generate($this->event(), $this->weeklyRule());

        $occurrenceWrites = [];
        DB::listen(function ($query) use (&$occurrenceWrites) {
            if (preg_match('/^(update|insert into|delete from) "?event_occurrences"?/i', $query->sql)) {
                $occurrenceWrites[] = $query->sql;
            }
        });

        $this->generator->generate($this->event(), $this->weeklyRule());

        $this->assertSame([], $occurrenceWrites, 'An identical rule must not write to event_occurrences');
    }

    public function test_extending_a_rule_keeps_matching_occurrences_and_appends_new_ones(): void
    {
        $this->generator->generate($this->event(), $this->weeklyRule(count: 3));
        $originalIds = array_column($this->liveOccurrences(), 'id');

        $this->generator->generate($this->event(), $this->weeklyRule(count: 5));
        $extended = $this->liveOccurrences();

        $this->assertCount(5, $extended);
        $this->assertSame(
            $originalIds,
            array_slice(array_column($extended, 'id'), 0, 3),
            'Existing occurrences matching the new rule must keep their ids'
        );
    }
}
