<?php

namespace Tests\Feature\BoxOffice;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\EventOccurrenceGeneratorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response as ResponseCodes;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class BoxOfficePinnedDateTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
    }

    public function test_changing_the_schedule_keeps_the_date_a_box_office_sells(): void
    {
        $generator = app(EventOccurrenceGeneratorService::class);
        $generator->generate(app(EventRepositoryInterface::class)->findById($this->eventId), $this->weeklyRule(3));
        $pinnedDateId = DB::table('event_occurrences')->where('event_id', $this->eventId)->whereNull('deleted_at')->orderByDesc('start_date')->value('id');
        $this->insertBoxOffice($pinnedDateId);

        $generator->generate(app(EventRepositoryInterface::class)->findById($this->eventId), $this->weeklyRule(2));

        $this->assertDatabaseHas('event_occurrences', ['id' => $pinnedDateId, 'deleted_at' => null, 'is_overridden' => true]);
    }

    public function test_the_date_a_box_office_sells_cannot_be_deleted(): void
    {
        $pinnedDateId = $this->insertOccurrence();
        $this->insertBoxOffice($pinnedDateId);

        $this->deleteJson(
            "/events/{$this->eventId}/occurrences/$pinnedDateId",
            [],
            ['Authorization' => 'Bearer '.JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId))],
        )
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('occurrence');

        $this->assertDatabaseHas('event_occurrences', ['id' => $pinnedDateId, 'deleted_at' => null]);
    }

    private function insertBoxOffice(int $occurrenceId): void
    {
        DB::table('box_offices')->insert([
            'event_id' => $this->eventId,
            'event_occurrence_id' => $occurrenceId,
            'short_id' => 'bo_'.uniqid(),
            'name' => 'Front door',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function weeklyRule(int $count): array
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
}
