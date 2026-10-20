<?php

namespace Tests\Feature\Http\Actions\EventOccurrences;

use HiEvents\Http\ResponseCodes;
use HiEvents\Jobs\Occurrence\GenerateOccurrencesJob;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class GenerateOccurrencesActionTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private string $authToken;

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->user, $this->authToken, $this->accountId] = $this->makeAuthenticatedUser();
        $this->organizerId = $this->makeOrganizer($this->accountId);
        $this->eventId = $this->makeEvent();
    }

    public function test_generate_dispatches_batch_and_returns_accepted(): void
    {
        Bus::fake();

        $response = $this->postJson(
            "/events/{$this->eventId}/occurrences/generate",
            ['recurrence_rule' => $this->weeklyRule()],
            $this->authHeaders(),
        );

        $response->assertStatus(ResponseCodes::HTTP_ACCEPTED);
        $this->assertSame('IN_PROGRESS', $response->json('status'));
        $this->assertNotEmpty($response->json('job_uuid'));

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 1
                && $batch->jobs->first() instanceof GenerateOccurrencesJob
                && $batch->jobs->first()->eventId === $this->eventId;
        });
    }

    public function test_generate_with_sync_queue_creates_occurrences_and_status_reports_finished(): void
    {
        config(['queue.default' => 'sync']);

        $response = $this->postJson(
            "/events/{$this->eventId}/occurrences/generate",
            ['recurrence_rule' => $this->weeklyRule()],
            $this->authHeaders(),
        );

        $response->assertStatus(ResponseCodes::HTTP_ACCEPTED);
        $this->assertSame('FINISHED', $response->json('status'));
        $jobUuid = $response->json('job_uuid');

        $this->assertSame(
            3,
            DB::table('event_occurrences')->where('event_id', $this->eventId)->whereNull('deleted_at')->count(),
        );
        $this->assertSame(
            'RECURRING',
            DB::table('events')->where('id', $this->eventId)->value('type'),
        );

        $status = $this->getJson(
            "/events/{$this->eventId}/occurrences/generate/status?job_uuid=$jobUuid",
            $this->authHeaders(),
        );

        $status->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame('FINISHED', $status->json('status'));
    }

    public function test_generate_rejects_over_limit_rule_without_dispatching(): void
    {
        Bus::fake();

        $response = $this->postJson(
            "/events/{$this->eventId}/occurrences/generate",
            [
                'recurrence_rule' => [
                    'frequency' => 'daily',
                    'interval' => 1,
                    'range' => ['type' => 'count', 'count' => 1200, 'start' => '2030-06-03'],
                    'times_of_day' => ['09:00', '12:00'],
                ],
            ],
            $this->authHeaders(),
        );

        $response->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        Bus::assertNothingBatched();
    }

    public function test_status_endpoint_returns_not_found_for_unknown_job(): void
    {
        $status = $this->getJson(
            "/events/{$this->eventId}/occurrences/generate/status?job_uuid=nonexistent",
            $this->authHeaders(),
        );

        $status->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame('NOT_FOUND', $status->json('status'));
    }

    public function test_status_endpoint_rejects_job_uuid_from_another_event(): void
    {
        $response = $this->postJson(
            "/events/{$this->eventId}/occurrences/generate",
            ['recurrence_rule' => $this->weeklyRule()],
            $this->authHeaders(),
        );
        $jobUuid = $response->json('job_uuid');

        $otherEventId = $this->makeEvent();

        $status = $this->getJson(
            "/events/$otherEventId/occurrences/generate/status?job_uuid=$jobUuid",
            $this->authHeaders(),
        );

        $status->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame('NOT_FOUND', $status->json('status'));
    }

    private function weeklyRule(): array
    {
        return [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['monday'],
            'range' => ['type' => 'count', 'count' => 3, 'start' => '2030-06-03'],
            'times_of_day' => ['19:00'],
        ];
    }

    private function makeAuthenticatedUser(): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $token = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);

        return [$user, $token, $accountId];
    }

    private function makeOrganizer(int $accountId): int
    {
        return DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Occurrence Organizer',
            'email' => 'organizer-'.uniqid().'@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'title' => 'Occurrence Test Event',
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function authHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
