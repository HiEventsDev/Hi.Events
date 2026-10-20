<?php

namespace Tests\Feature\Http\Actions\Admin;

use HiEvents\Jobs\Account\ExecuteAccountDeletionJob;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Event\EventSpamCheckContentService;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class SpamEventsTest extends TestCase
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

        [$this->user, $this->authToken, $this->accountId] = $this->makeAuthenticatedUser('SUPERADMIN');
        $this->organizerId = $this->makeOrganizer();
        $this->eventId = $this->makeEvent('PENDING_MANUAL_REVIEW');
        $this->makeSpamCheck($this->eventId, 'FLAGGED');
    }

    public function test_non_superadmin_cannot_access_spam_event_endpoints(): void
    {
        [, $adminToken] = $this->makeAuthenticatedUser('ADMIN');

        $this->getJson('/admin/spam-events', $this->authHeaders($adminToken))->assertStatus(403);
        $this->postJson("/admin/spam-events/{$this->eventId}/approve", [], $this->authHeaders($adminToken))->assertStatus(403);
        $this->postJson("/admin/spam-events/{$this->eventId}/confirm-spam", [], $this->authHeaders($adminToken))->assertStatus(403);
        $this->postJson("/admin/spam-events/{$this->eventId}/delete", [], $this->authHeaders($adminToken))->assertStatus(403);
    }

    public function test_list_returns_flagged_events_with_verdict(): void
    {
        $response = $this->getJson('/admin/spam-events', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($this->eventId, $response->json('data.0.event_id'));
        $this->assertSame('Spam Test Event', $response->json('data.0.event_title'));
        $this->assertSame(0.95, $response->json('data.0.verdict.confidence'));
        $this->assertSame(['Phishing attempt'], $response->json('data.0.verdict.reasons'));
    }

    public function test_list_search_filters_by_event_title(): void
    {
        $this->getJson('/admin/spam-events?search=Spam+Test', $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->getJson('/admin/spam-events?search=NoSuchEvent', $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_approve_publishes_event_and_marks_check_approved(): void
    {
        $this->postJson("/admin/spam-events/{$this->eventId}/approve", [], $this->authHeaders())
            ->assertStatus(200);

        $this->assertSame('LIVE', DB::table('events')->where('id', $this->eventId)->value('status'));

        $check = DB::table('event_spam_checks')->where('event_id', $this->eventId)->first();
        $this->assertSame('APPROVED', $check->status);
        $this->assertSame($this->user->id, $check->reviewed_by_user_id);
        $this->assertNotNull($check->reviewed_at);
        $this->assertNotSame(hash('sha256', 'content'), $check->content_hash);
        $this->assertSame($this->currentContentHash(), $check->content_hash);
    }

    public function test_approve_twice_returns_not_found(): void
    {
        $this->postJson("/admin/spam-events/{$this->eventId}/approve", [], $this->authHeaders())
            ->assertStatus(200);

        $this->postJson("/admin/spam-events/{$this->eventId}/approve", [], $this->authHeaders())
            ->assertStatus(404);
    }

    public function test_confirm_spam_keeps_event_pending_and_marks_check_confirmed(): void
    {
        $this->postJson("/admin/spam-events/{$this->eventId}/confirm-spam", [], $this->authHeaders())
            ->assertStatus(200);

        $this->assertSame('PENDING_MANUAL_REVIEW', DB::table('events')->where('id', $this->eventId)->value('status'));
        $this->assertSame('CONFIRMED_SPAM', DB::table('event_spam_checks')->where('event_id', $this->eventId)->value('status'));

        $this->getJson('/admin/spam-events', $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_list_includes_event_slug_for_public_page_link(): void
    {
        $this->getJson('/admin/spam-events', $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.0.event_slug', 'spam-test-event');
    }

    public function test_delete_removes_event_and_confirms_spam_without_touching_account(): void
    {
        Queue::fake();

        $this->postJson("/admin/spam-events/{$this->eventId}/delete", [], $this->authHeaders())
            ->assertStatus(200);

        $this->assertNotNull(DB::table('events')->where('id', $this->eventId)->value('deleted_at'));
        $this->assertSame('CONFIRMED_SPAM', DB::table('event_spam_checks')->where('event_id', $this->eventId)->value('status'));
        $this->assertSame(0, DB::table('account_deletion_requests')->where('account_id', $this->accountId)->count());
        Queue::assertNotPushed(ExecuteAccountDeletionJob::class);

        $this->getJson('/admin/spam-events', $this->authHeaders())->assertJsonCount(0, 'data');
    }

    public function test_delete_with_account_queues_immediate_account_deletion(): void
    {
        Queue::fake();
        $spammerAccountId = $this->moveEventToNewAccount();

        $this->postJson("/admin/spam-events/{$this->eventId}/delete", ['delete_account' => true], $this->authHeaders())
            ->assertStatus(200);

        $this->assertNotNull(DB::table('events')->where('id', $this->eventId)->value('deleted_at'));

        $deletionRequest = DB::table('account_deletion_requests')->where('account_id', $spammerAccountId)->first();
        $this->assertNotNull($deletionRequest);
        $this->assertSame('REQUESTED', $deletionRequest->status);
        $this->assertSame('ADMIN', $deletionRequest->initiated_by);

        Queue::assertPushed(
            ExecuteAccountDeletionJob::class,
            fn (ExecuteAccountDeletionJob $job) => $job->deletionRequestId === $deletionRequest->id,
        );
    }

    public function test_delete_with_account_is_refused_for_account_with_superadmin_member(): void
    {
        Queue::fake();

        $this->postJson("/admin/spam-events/{$this->eventId}/delete", ['delete_account' => true], $this->authHeaders())
            ->assertStatus(409);

        $this->assertNull(DB::table('events')->where('id', $this->eventId)->value('deleted_at'));
        $this->assertSame(0, DB::table('account_deletion_requests')->where('account_id', $this->accountId)->count());
        Queue::assertNotPushed(ExecuteAccountDeletionJob::class);
    }

    public function test_delete_returns_not_found_for_event_not_awaiting_review(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);

        $this->postJson("/admin/spam-events/{$this->eventId}/delete", [], $this->authHeaders())
            ->assertStatus(404);

        $this->assertNull(DB::table('events')->where('id', $this->eventId)->value('deleted_at'));
    }

    public function test_delete_keeps_event_when_account_deletion_is_refused(): void
    {
        Queue::fake();
        $spammerAccountId = $this->moveEventToNewAccount();

        DB::table('account_deletion_requests')->insert([
            'account_id' => $spammerAccountId,
            'requested_by_user_id' => $this->user->id,
            'initiated_by' => 'USER',
            'status' => 'REQUESTED',
            'expected_outcome' => 'HARD_DELETE',
            'scheduled_deletion_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/admin/spam-events/{$this->eventId}/delete", ['delete_account' => true], $this->authHeaders())
            ->assertStatus(409);

        $this->assertNull(DB::table('events')->where('id', $this->eventId)->value('deleted_at'));
        $this->assertSame('FLAGGED', DB::table('event_spam_checks')->where('event_id', $this->eventId)->value('status'));
        Queue::assertNotPushed(ExecuteAccountDeletionJob::class);
    }

    public function test_organizer_cannot_send_pending_manual_review_status(): void
    {
        $this->putJson(
            "/events/{$this->eventId}/status",
            ['status' => 'PENDING_MANUAL_REVIEW'],
            $this->authHeaders(),
        )->assertStatus(422);
    }

    public function test_status_change_is_blocked_while_pending_review(): void
    {
        $this->putJson(
            "/events/{$this->eventId}/status",
            ['status' => 'DRAFT'],
            $this->authHeaders(),
        )->assertStatus(422);

        $this->assertSame('PENDING_MANUAL_REVIEW', DB::table('events')->where('id', $this->eventId)->value('status'));
    }

    private function makeAuthenticatedUser(string $role): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        DB::table('account_users')
            ->where('account_id', $accountId)
            ->where('user_id', $user->id)
            ->update(['role' => $role]);

        $token = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);

        return [$user, $token, $accountId];
    }

    private function moveEventToNewAccount(): int
    {
        [, , $spammerAccountId] = $this->makeAuthenticatedUser('ADMIN');
        DB::table('events')->where('id', $this->eventId)->update(['account_id' => $spammerAccountId]);

        return $spammerAccountId;
    }

    private function makeOrganizer(): int
    {
        return DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Spam Test Organizer',
            'email' => 'organizer-'.uniqid().'@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(string $status): int
    {
        return DB::table('events')->insertGetId([
            'title' => 'Spam Test Event',
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'organizer_id' => $this->organizerId,
            'status' => $status,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function currentContentHash(): string
    {
        $contentService = $this->app->make(EventSpamCheckContentService::class);

        return $this->app->make(EventSpamCheckService::class)->hashContent(
            $contentService->buildForEvent($contentService->loadEvent($this->eventId)),
        );
    }

    private function makeSpamCheck(int $eventId, string $status): int
    {
        return DB::table('event_spam_checks')->insertGetId([
            'event_id' => $eventId,
            'status' => $status,
            'verdict' => json_encode([
                'is_spam' => true,
                'confidence' => 0.95,
                'reasons' => ['Phishing attempt'],
                'model' => 'claude-haiku-4-5',
            ]),
            'content_hash' => hash('sha256', 'content'),
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function authHeaders(?string $token = null): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.($token ?? $this->authToken)];
    }
}
