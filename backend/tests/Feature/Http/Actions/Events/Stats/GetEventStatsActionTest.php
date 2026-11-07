<?php

namespace Tests\Feature\Http\Actions\Events\Stats;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class GetEventStatsActionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private string $authToken;

    private int $ownDateId;

    private int $otherEventDateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));

        $otherEventId = $this->insertEvent(EventType::RECURRING->name);
        $this->eventId = $otherEventId;
        $this->otherEventDateId = $this->insertOccurrence();

        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        $this->ownDateId = $this->insertOccurrence();
    }

    public function test_stats_for_one_of_the_event_dates_succeed(): void
    {
        $this->getJson($this->statsUrl($this->ownDateId), $this->headers())
            ->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_a_date_from_another_event_is_rejected(): void
    {
        $this->getJson($this->statsUrl($this->otherEventDateId), $this->headers())
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['occurrence_id']);
    }

    public function test_a_deleted_date_is_rejected(): void
    {
        DB::table('event_occurrences')->where('id', $this->ownDateId)->update(['deleted_at' => now()]);

        $this->getJson($this->statsUrl($this->ownDateId), $this->headers())
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['occurrence_id']);
    }

    private function statsUrl(int $occurrenceId): string
    {
        return "/events/{$this->eventId}/stats?occurrence_id=$occurrenceId";
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
