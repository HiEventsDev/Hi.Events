<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\SendScheduledCampaignsJob;
use HiEvents\Enterprise\Crm\Mail\CampaignNotSentMail;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignReviewHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => true]);
        $this->setUpCrmApi();
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);
    }

    public static function eventsThatStoppedOfferingEarlyAccess(): array
    {
        return [
            'event was unpublished' => ['unpublished', 'Publish the event before sending people an early access link.'],
            'event was deleted' => ['deleted', 'The event linked to this campaign no longer exists.'],
            'tickets were deleted' => ['tickets_deleted', 'The early access tickets for this campaign were deleted'],
        ];
    }

    #[DataProvider('eventsThatStoppedOfferingEarlyAccess')]
    public function test_approving_an_early_access_campaign_whose_event_can_no_longer_offer_it_is_refused_with_the_reason(string $case, string $expectedReason): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $productIds = DB::table('products')->where('event_id', $this->eventId)->orderBy('id')->pluck('id')->all();
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'event_id' => $this->eventId, 'early_access_product_ids' => json_encode($productIds)]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');
        $reviewedVersion = DB::table('crm_campaigns')->where('id', $campaignId)->value('updated_at');

        match ($case) {
            'unpublished' => DB::table('events')->where('id', $this->eventId)->update(['status' => 'DRAFT']),
            'deleted' => DB::table('events')->where('id', $this->eventId)->update(['deleted_at' => now()]),
            'tickets_deleted' => DB::table('products')->where('event_id', $this->eventId)->update(['deleted_at' => now()]),
        };

        $response = $this->json(
            'POST',
            "/admin/crm/campaigns/$campaignId/approve",
            ['reviewed_version' => $reviewedVersion],
            $this->memberHeaders($this->addAccountUser($this->accountId, Role::SUPERADMIN)),
        )->assertStatus(409);

        $this->assertStringContainsString($expectedReason, (string) $response->json('message'));
        $campaign = $this->campaign($campaignId);
        $this->assertSame(['PENDING_REVIEW', null, null], [$campaign->status, $campaign->promo_code_id, $campaign->approved_at]);
        $this->assertSame(1, DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->where('status', 'PENDING')->count());
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_approving_a_campaign_that_changed_since_the_queue_was_loaded_asks_the_reviewer_to_look_again(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'updated_at' => now()]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');

        $this->json(
            'POST',
            "/admin/crm/campaigns/$campaignId/approve",
            ['reviewed_version' => now()->subMinutes(5)->toDateTimeString()],
            $this->memberHeaders($this->addAccountUser($this->accountId, Role::SUPERADMIN)),
        )
            ->assertStatus(409)
            ->assertJsonPath('message', 'This campaign changed after you opened it. Reload the review queue and check it again.');

        $this->assertSame('PENDING_REVIEW', $this->campaign($campaignId)->status);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_a_campaign_is_approved_with_the_version_the_review_queue_returned(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'updated_at' => now()->subMinutes(5)]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');
        $headers = fn (): array => $this->memberHeaders($this->addAccountUser($this->accountId, Role::SUPERADMIN));

        $listed = collect($this->json('GET', '/admin/crm/campaigns/pending-review', [], $headers())->assertOk()->json('data'))
            ->firstWhere('id', $campaignId);

        $this->json('POST', "/admin/crm/campaigns/$campaignId/approve", ['reviewed_version' => $listed['updated_at']], $headers())->assertOk();

        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        Bus::assertDispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->campaignId === $campaignId);
    }

    public function test_approving_a_scheduled_campaign_long_after_its_time_returns_it_to_draft_instead_of_sending_it_late(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        Mail::fake();
        $lateBy = CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS * 60 + 5;
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'scheduled_at' => now()->subMinutes($lateBy)]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');

        $response = $this->approve($campaignId)->assertStatus(409);

        $this->assertSame('This campaign can no longer be sent: it waited too long for review, or its automation was switched off. It was withdrawn instead of approved.', $response->json('message'));
        $campaign = $this->campaign($campaignId);
        $this->assertSame(['DRAFT', 'SCHEDULED_SEND_EXPIRED', null, null], [$campaign->status, $campaign->status_reason, $campaign->scheduled_at, $campaign->approved_at]);
        $this->assertSame(0, DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->count());
        Mail::assertQueued(CampaignNotSentMail::class);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_approving_a_scheduled_campaign_shortly_after_its_time_sends_it(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $lateBy = CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS * 60 - 5;
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'scheduled_at' => now()->subMinutes($lateBy)]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');

        $this->approve($campaignId)->assertOk();

        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        Bus::assertDispatched(DispatchCampaignJob::class);
    }

    public function test_approving_an_automation_email_that_waited_too_long_cancels_the_run_instead_of_sending_it_late(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        Mail::fake();
        $waitedHours = CampaignLaunchService::AUTOMATION_REVIEW_WINDOW_HOURS + 1;
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'automation_id' => 999999, 'automation_run_key' => 'date:stale', 'launched_at' => now()->subHours($waitedHours)]);
        $recipientId = $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');

        $this->approve($campaignId)->assertStatus(409);

        $campaign = $this->campaign($campaignId);
        $this->assertSame(['CANCELLED', 'REVIEW_EXPIRED', null], [$campaign->status, $campaign->status_reason, $campaign->approved_at]);
        $this->assertSame('SKIPPED', DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('status'));
        Bus::assertNotDispatched(DispatchCampaignJob::class);
        Mail::assertNothingQueued();
    }

    public function test_an_automation_run_that_expired_in_review_says_so_on_the_automation(): void
    {
        $waitedHours = CampaignLaunchService::AUTOMATION_REVIEW_WINDOW_HOURS + 1;
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'automation_id' => $this->automationId, 'automation_run_key' => 'date:stale', 'launched_at' => now()->subHours($waitedHours)]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');

        SendScheduledCampaignsJob::dispatchSync();

        $this->assertSame('CANCELLED', $this->campaign($campaignId)->status);
        $automation = collect($this->crm('GET', 'automations', [], $this->ownerHeaders())->assertOk()->json('data'))->firstWhere('id', $this->automationId);
        $this->assertSame(
            ['REVIEW_EXPIRED', ['hours' => CampaignLaunchService::AUTOMATION_REVIEW_WINDOW_HOURS]],
            [$automation['last_failure_reason'], $automation['last_failure_reason_params']],
        );
    }

    public function test_an_automation_email_reviewed_within_a_day_is_sent(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $waitedHours = CampaignLaunchService::AUTOMATION_REVIEW_WINDOW_HOURS - 1;
        DB::table('crm_automations')->where('id', $this->automationId)->update(['is_enabled' => true, 'enabled_at' => now()->subDays(2)]);
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'automation_id' => $this->automationId, 'automation_run_key' => 'date:fresh', 'launched_at' => now()->subHours($waitedHours)]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');

        $this->approve($campaignId)->assertOk();

        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
    }

    public function test_a_campaign_sent_straight_away_never_expires_in_review(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'launched_at' => now()->subDays(5)]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');

        $this->approve($campaignId)->assertOk();

        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
    }

    public function test_reviews_that_waited_too_long_leave_the_queue_without_a_reviewer(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        Mail::fake();
        $scheduledWindow = CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS * 60;
        $pending = ['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN'];
        $missedId = $this->insertCampaign([...$pending, 'scheduled_at' => now()->subMinutes($scheduledWindow + 5)]);
        $stillInTimeId = $this->insertCampaign([...$pending, 'scheduled_at' => now()->subMinutes($scheduledWindow - 5)]);
        $upcomingId = $this->insertCampaign([...$pending, 'scheduled_at' => now()->addDay()]);
        $immediateId = $this->insertCampaign([...$pending, 'launched_at' => now()->subDays(3)]);
        $staleRunId = $this->insertCampaign([...$pending, 'automation_id' => 999999, 'automation_run_key' => 'date:stale', 'launched_at' => now()->subHours(CampaignLaunchService::AUTOMATION_REVIEW_WINDOW_HOURS + 1)]);
        $freshRunId = $this->insertCampaign([...$pending, 'automation_id' => 999999, 'automation_run_key' => 'date:fresh', 'launched_at' => now()->subHour()]);

        SendScheduledCampaignsJob::dispatchSync();

        $this->assertSame(
            [$missedId => 'DRAFT', $stillInTimeId => 'PENDING_REVIEW', $upcomingId => 'PENDING_REVIEW', $immediateId => 'PENDING_REVIEW', $staleRunId => 'CANCELLED', $freshRunId => 'PENDING_REVIEW'],
            DB::table('crm_campaigns')->whereIn('id', [$missedId, $stillInTimeId, $upcomingId, $immediateId, $staleRunId, $freshRunId])->orderBy('id')->pluck('status', 'id')->all(),
        );
        Mail::assertQueuedCount(1);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    private function approve(int $campaignId): TestResponse
    {
        return $this->json(
            'POST',
            "/admin/crm/campaigns/$campaignId/approve",
            ['reviewed_version' => DB::table('crm_campaigns')->where('id', $campaignId)->value('updated_at')],
            $this->memberHeaders($this->addAccountUser($this->accountId, Role::SUPERADMIN)),
        );
    }
}
