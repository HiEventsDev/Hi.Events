<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPendingReviewJob;
use HiEvents\Enterprise\Crm\Jobs\SendCampaignEmailJob;
use HiEvents\Enterprise\Crm\Mail\CampaignPendingReviewMail;
use HiEvents\Enterprise\Crm\Mail\CampaignRejectedMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSendGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class CampaignHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => false]);
        $this->setUpCrmApi();
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);
    }

    public function test_a_campaign_is_created_as_a_draft_with_its_body_purified(): void
    {
        $created = $this->crm('POST', 'campaigns', [
            'name' => '  Summer launch ',
            'subject' => ' Tickets are live ',
            'body' => '<p>Hello</p><script>alert(1)</script><img src="x" onerror="alert(2)">',
            'preset' => 'subscribers',
        ], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Summer launch')
            ->assertJsonPath('data.subject', 'Tickets are live')
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.is_editable', true)
            ->assertJsonPath('data.preset', 'subscribers')
            ->assertJsonPath('data.segment_id', null)
            ->assertJsonPath('data.recipients_count', 0)
            ->json('data');

        $stored = $this->campaign($created['id']);
        $this->assertStringContainsString('<p>Hello</p>', $stored->body);
        $this->assertStringNotContainsString('script', $stored->body);
        $this->assertStringNotContainsString('onerror', $stored->body);
        $this->assertSame([$this->accountId, $this->organizerId, $this->owner->id], [(int) $stored->account_id, (int) $stored->organizer_id, (int) $stored->created_by_user_id]);
    }

    public function test_a_campaign_keeps_only_one_kind_of_audience(): void
    {
        $definition = ['match' => 'all', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]];

        $bySegment = $this->crm('POST', 'campaigns', ['name' => 'A', 'segment_id' => $this->segmentId, 'preset' => 'subscribers', 'definition' => $definition], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.segment_id', $this->segmentId)
            ->assertJsonPath('data.segment_name', 'Buyers')
            ->assertJsonPath('data.preset', null)
            ->assertJsonPath('data.definition', null)
            ->json('data.id');

        $this->crm('PUT', "campaigns/$bySegment", ['name' => 'A', 'definition' => $definition], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.segment_id', null)
            ->assertJsonPath('data.preset', null)
            ->assertJsonPath('data.definition.match', 'all')
            ->assertJsonPath('data.definition.rules.0.field', 'orders_count')
            ->assertJsonPath('data.definition.rules.0.operator', 'gte')
            ->assertJsonPath('data.definition.rules.0.value', 1);
    }

    public static function invalidCampaigns(): array
    {
        return [
            'no name' => [['subject' => 'Hi'], 422],
            'name too long' => [['name' => str_repeat('a', 151)], 422],
            'subject too long' => [['name' => 'A', 'subject' => str_repeat('a', 201)], 422],
            'unknown preset' => [['name' => 'A', 'preset' => 'everyone'], 404],
            'missing segment' => [['name' => 'A', 'segment_id' => 999999999], 404],
            'missing event' => [['name' => 'A', 'event_id' => 999999999], 404],
            'definition with an unknown field' => [['name' => 'A', 'definition' => ['rules' => [['field' => 'password', 'operator' => 'eq', 'value' => 1]]]], 422],
            'definition that is not a list of rules' => [['name' => 'A', 'definition' => 'orders_count'], 422],
        ];
    }

    #[DataProvider('invalidCampaigns')]
    public function test_invalid_campaigns_are_rejected_on_create_and_update_and_change_nothing(array $body, int $status): void
    {
        $before = $this->crmState();

        $this->crm('POST', 'campaigns', $body, $this->ownerHeaders())->assertStatus($status);
        $this->crm('PUT', "campaigns/{$this->campaignId}", $body, $this->ownerHeaders())->assertStatus($status);

        $this->assertSame($before, $this->crmState());
    }

    public function test_members_without_view_financials_cannot_target_a_campaign_by_spend(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spend = ['match' => 'all', 'rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD']]];

        $this->crm('POST', 'campaigns', ['name' => 'Big spenders', 'definition' => $spend], $this->memberHeaders($member))->assertStatus(422);
        $this->crm('POST', 'campaigns', ['name' => 'Big spenders', 'definition' => $spend], $this->ownerHeaders())->assertCreated();
    }

    public function test_members_without_view_financials_see_no_spend_rules_and_no_recipients_of_a_spend_campaign(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spendRule = ['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD'];
        $countRule = ['field' => 'orders_count', 'operator' => 'gte', 'value' => 1];
        $definition = ['match' => 'all', 'rules' => [$spendRule, $countRule]];
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour(), 'segment_definition' => json_encode($definition)]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com', ['status' => 'SENT']);
        $segmentId = $this->insertSegment($this->organizerId, 'Big spenders', [$spendRule]);

        $forOwner = $this->crm('GET', "campaigns/$campaignId", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.has_restricted_rules', false);
        $this->assertEquals([$spendRule, $countRule], $forOwner->json('data.definition.rules'));
        $this->crm('GET', "campaigns/$campaignId/recipients", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.0.email', 'ada@example.com');

        $forMember = $this->crm('GET', "campaigns/$campaignId", [], $this->memberHeaders($member))
            ->assertOk()
            ->assertJsonPath('data.has_restricted_rules', true)
            ->assertJsonPath('data.definition.match', 'all');
        $this->assertEquals([$countRule], $forMember->json('data.definition.rules'));
        $this->crm('GET', "campaigns/$campaignId/recipients", [], $this->memberHeaders($member))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
        $this->crm('GET', "campaigns/{$this->campaignId}/recipients", [], $this->memberHeaders($member))->assertOk();

        $segments = collect($this->crm('GET', 'segments', [], $this->memberHeaders($member))->assertOk()->json('data'));
        $segment = $segments->firstWhere('id', $segmentId);
        $this->assertTrue($segment['has_restricted_rules']);
        $this->assertSame([], $segment['definition']['rules']);
        $this->assertFalse($segments->firstWhere('id', $this->segmentId)['has_restricted_rules']);
    }

    public function test_the_recipient_list_is_paginated_like_other_lists(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour()]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com', ['status' => 'SENT']);

        $this->crm('GET', "campaigns/$campaignId/recipients", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonStructure(['data' => [['id', 'contact_id', 'email', 'status']], 'links', 'meta']);
    }

    public function test_a_campaign_sent_by_an_automation_cannot_be_deleted(): void
    {
        foreach (['DRAFT', 'CANCELLED', 'REJECTED'] as $status) {
            $campaignId = $this->insertCampaign(['status' => $status, 'automation_id' => 999999, 'automation_run_key' => "date:$status"]);

            $this->crm('DELETE', "campaigns/$campaignId", [], $this->ownerHeaders())->assertStatus(409);

            $this->assertNotNull($this->campaign($campaignId), $status);
        }
    }

    public function test_rejecting_an_automation_campaign_cancels_it_and_keeps_its_recipients_as_skipped_so_it_is_not_retried(): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        $automationCampaignId = $this->insertCampaign(['automation_id' => 999999, 'automation_run_key' => 'date:today']);
        $this->assertSame('PENDING_REVIEW', app(CampaignLaunchService::class)->launch($this->campaignRepository()->findById($automationCampaignId))->value);
        $this->assertSame(1, (int) $this->campaign($automationCampaignId)->recipients_count);

        $this->assertTrue(app(CampaignLaunchService::class)->reject($this->campaignRepository()->findById($automationCampaignId), null));

        $campaign = $this->campaign($automationCampaignId);
        $this->assertSame(['CANCELLED', 'REJECTED_BY_REVIEW', null], [$campaign->status, $campaign->status_reason, $campaign->launched_at]);
        $this->assertSame(['SKIPPED'], DB::table('crm_campaign_recipients')->where('campaign_id', $automationCampaignId)->pluck('status')->all());
        $this->crm('DELETE', "campaigns/$automationCampaignId", [], $this->ownerHeaders())->assertStatus(409);
    }

    public static function importReviews(): array
    {
        return [
            'imported after the approved campaign reached them' => [true, 1, true, 'PENDING_REVIEW', 'RECENT_IMPORT'],
            'reached by an approved campaign after the import' => [true, -1, true, 'SENDING', null],
            'imported before an approved campaign that never reached them' => [true, -1, false, 'PENDING_REVIEW', 'RECENT_IMPORT'],
            'imported after the approval, self-hosted' => [false, 1, true, 'SENDING', null],
        ];
    }

    #[DataProvider('importReviews')]
    public function test_in_saas_mode_contacts_whose_only_opt_in_is_an_unreviewed_import_need_review(bool $isSaas, int $importedHoursAfterApproval, bool $approvedCampaignReachedThem, string $expectedStatus, ?string $reason): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => $isSaas]);
        $approvedAt = now()->subDays(2);
        $approvedCampaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => $approvedAt, 'launched_at' => $approvedAt, 'approved_at' => $approvedAt]);
        $importedId = $this->insertContact('imported@example.com', ['email_marketing_status' => 'SUBSCRIBED', 'source' => 'IMPORT']);
        DB::table('crm_consent_events')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $importedId,
            'email' => 'imported@example.com',
            'action' => 'OPT_IN',
            'source_type' => 'IMPORT',
            'source_id' => 1,
            'occurred_at' => $approvedAt->copy()->addHours($importedHoursAfterApproval),
        ]);

        if ($approvedCampaignReachedThem) {
            $this->insertRecipient($approvedCampaignId, $importedId, 'imported@example.com', ['status' => 'SENT']);
        }

        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', $expectedStatus)
            ->assertJsonPath('data.status_reason', $reason);
    }

    public function test_a_recent_import_does_not_need_review_when_the_campaign_does_not_reach_it(): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        $approvedAt = now()->subDays(2);
        $this->insertCampaign(['status' => 'SENT', 'sent_at' => $approvedAt, 'launched_at' => $approvedAt, 'approved_at' => $approvedAt]);
        $importedId = $this->insertContact('imported@example.com', ['email_marketing_status' => 'SUBSCRIBED', 'source' => 'IMPORT']);
        DB::table('crm_consent_events')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $importedId,
            'email' => 'imported@example.com',
            'action' => 'OPT_IN',
            'source_type' => 'IMPORT',
            'source_id' => 1,
            'occurred_at' => now()->subHour(),
        ]);
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['segment_id' => $this->segmentId]);

        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'SENDING');
    }

    public function test_approving_a_campaign_records_when_it_was_approved(): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'PENDING_REVIEW');
        $campaign = $this->campaignRepository()->findById($this->campaignId);

        $this->assertTrue(app(CampaignLaunchService::class)->approve($campaign, Carbon::parse($campaign->getUpdatedAt())));

        $this->assertSame(now()->toDateTimeString(), $this->campaign($this->campaignId)->approved_at);
    }

    public function test_campaigns_are_listed_newest_first_for_the_organizer_only(): void
    {
        $newerId = $this->insertCampaign(['name' => 'Newer']);

        $this->crm('GET', 'campaigns', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $newerId)
            ->assertJsonPath('data.1.id', $this->campaignId);
        $this->crm('GET', 'campaigns?per_page=1&page=2', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->campaignId);
    }

    public function test_campaigns_can_be_searched_by_subject_or_name_and_filtered_by_source(): void
    {
        $autumnId = $this->insertCampaign(['name' => 'Internal: autumn', 'subject' => 'Autumn 100% sale']);
        $labelledId = $this->insertCampaign(['name' => 'Autumn label', 'subject' => 'Something else']);
        $automatedId = $this->insertCampaign(['status' => 'SENT', 'subject' => 'Autumn automated', 'automation_id' => 999999, 'automation_run_key' => 'date:one']);
        $rejectedAutomatedId = $this->insertCampaign(['status' => 'CANCELLED', 'status_reason' => 'REJECTED_BY_REVIEW', 'automation_id' => 999999, 'automation_run_key' => 'date:two']);
        $this->insertCampaign(['status' => 'CANCELLED', 'status_reason' => 'AUTOMATION_SKIPPED', 'automation_id' => 999999, 'automation_run_key' => 'date:three']);
        $this->insertCampaign(['subject' => 'Autumn elsewhere'], $this->otherOrganizerId);

        $ids = fn (string $query) => array_column($this->crm('GET', 'campaigns'.$query, [], $this->ownerHeaders())->assertOk()->json('data'), 'id');

        $this->assertSame([$rejectedAutomatedId, $automatedId, $labelledId, $autumnId, $this->campaignId], $ids(''));
        $this->assertSame([$automatedId, $labelledId, $autumnId], $ids('?query=autumn'));
        $this->assertSame([$autumnId], $ids('?query='.rawurlencode('100%')));
        $this->assertSame([], $ids('?query='.rawurlencode('_%')));
        $this->assertSame([$automatedId, $labelledId, $autumnId], $ids('?query=AUTUMN'));
        $this->assertSame([$rejectedAutomatedId, $automatedId], $ids('?source=automation'));
        $this->assertSame([$automatedId], $ids('?source=automation&folder=sent&query=autumn'));
        $this->assertSame([$rejectedAutomatedId, $automatedId, $labelledId, $autumnId, $this->campaignId], $ids('?source=unknown'));

        foreach (['', '?source=automation&folder=sent'] as $query) {
            $this->crm('GET', 'campaigns'.$query, [], $this->ownerHeaders())
                ->assertJsonPath('meta.folders', ['drafts' => 3, 'scheduled' => 0, 'sent' => 2, 'automated' => 2]);
        }
    }

    public function test_revenue_is_only_shown_to_members_who_can_view_financials(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $sentId = $this->sentCampaignWithAnAttributedOrder();

        foreach (["campaigns/$sentId" => 'data', 'campaigns' => 'data.0'] as $path => $key) {
            $forOwner = $this->crm('GET', $path, [], $this->ownerHeaders())->assertOk()->json($key);
            $this->assertSame($sentId, $forOwner['id']);
            $this->assertEquals(
                [['currency' => 'USD', 'attributed_orders' => 1, 'attributed_revenue' => 100, 'influenced_orders' => 0, 'influenced_revenue' => 0]],
                $forOwner['revenue'],
                $path,
            );

            $forMember = $this->crm('GET', $path, [], $this->memberHeaders($member))->assertOk()->json($key);
            $this->assertSame($sentId, $forMember['id']);
            $this->assertArrayNotHasKey('revenue', $forMember, $path);
            $this->assertSame(1, $forMember['stats']['sent'], $path);
        }

        $this->crm('POST', "campaigns/$sentId/cancel", [], $this->memberHeaders($member))->assertStatus(409);
        $draft = $this->crm('PUT', "campaigns/{$this->campaignId}", ['name' => 'Renamed'], $this->memberHeaders($member))->assertOk()->json('data');
        $this->assertArrayNotHasKey('revenue', $draft);
    }

    public static function lockedStatuses(): array
    {
        return [
            'pending review' => ['PENDING_REVIEW'],
            'sending' => ['SENDING'],
            'sent' => ['SENT'],
            'paused' => ['PAUSED'],
            'cancelled' => ['CANCELLED'],
        ];
    }

    #[DataProvider('lockedStatuses')]
    public function test_a_campaign_can_no_longer_be_edited_or_sent_again_once_it_has_left_the_draft_stages(string $status): void
    {
        Bus::fake();
        $campaignId = $this->insertCampaign(['status' => $status, 'name' => 'Locked']);
        $before = $this->crmState();

        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'Changed', 'subject' => 'Changed', 'body' => '<p>Changed</p>'], $this->ownerHeaders())->assertStatus(409);
        $this->crm('POST', "campaigns/$campaignId/send", [], $this->ownerHeaders())->assertStatus(422);
        $this->crm('POST', "campaigns/$campaignId/send", ['scheduled_at' => now()->addDay()->toDateTimeString()], $this->ownerHeaders())->assertStatus(422);

        $this->assertSame($before, $this->crmState());
        Bus::assertNothingDispatched();
    }

    public static function editableStatuses(): array
    {
        return [
            'draft' => ['DRAFT'],
            'rejected' => ['REJECTED'],
        ];
    }

    #[DataProvider('editableStatuses')]
    public function test_a_draft_or_rejected_campaign_can_be_edited_and_sent(string $status): void
    {
        Bus::fake();
        $campaignId = $this->insertCampaign(['status' => $status, 'status_reason' => 'REJECTED_BY_REVIEW']);

        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'Changed', 'subject' => 'Changed', 'body' => '<p>Changed</p>'], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', $status);
        $this->crm('POST', "campaigns/$campaignId/send", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'SENDING')
            ->assertJsonPath('data.status_reason', null)
            ->assertJsonPath('data.scheduled_at', null)
            ->assertJsonPath('data.is_editable', false);

        Bus::assertDispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->campaignId === $campaignId);
        Bus::assertNotDispatched(NotifyCampaignPendingReviewJob::class);
    }

    public function test_a_scheduled_campaign_is_read_only_until_it_is_moved_back_to_draft(): void
    {
        Bus::fake();
        $campaignId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->addDay()]);

        $this->crm('GET', "campaigns/$campaignId", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.is_editable', false);
        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'Changed', 'subject' => 'Changed', 'body' => '<p>Changed</p>'], $this->ownerHeaders())->assertStatus(409);
        $this->crm('POST', "campaigns/$campaignId/send", [], $this->ownerHeaders())->assertStatus(422);
        Bus::assertNothingDispatched();

        $this->crm('POST', "campaigns/$campaignId/cancel", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'Changed', 'subject' => 'Changed', 'body' => '<p>Changed</p>'], $this->ownerHeaders())->assertOk();
    }

    public static function deletableStatuses(): array
    {
        return [
            'draft' => ['DRAFT', null, 204],
            'scheduled' => ['SCHEDULED', null, 204],
            'rejected' => ['REJECTED', null, 204],
            'sent to nobody' => ['SENT', null, 204],
            'cancelled before anyone was snapshotted' => ['CANCELLED', null, 204],
            'pending review' => ['PENDING_REVIEW', 'PENDING', 409],
            'draft that still has recipients' => ['DRAFT', 'PENDING', 409],
            'sending' => ['SENDING', 'SENT', 409],
            'sent' => ['SENT', 'SENT', 409],
            'sent with only skipped recipients' => ['SENT', 'SKIPPED', 409],
            'paused' => ['PAUSED', 'SENT', 409],
            'cancelled after some were sent' => ['CANCELLED', 'SENT', 409],
        ];
    }

    #[DataProvider('deletableStatuses')]
    public function test_a_campaign_can_be_deleted_only_while_it_has_no_recipients(string $status, ?string $recipientStatus, int $expected): void
    {
        $campaignId = $this->insertCampaign(['status' => $status]);
        if ($recipientStatus !== null) {
            $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com', ['status' => $recipientStatus]);
        }

        $response = $this->crm('DELETE', "campaigns/$campaignId", [], $this->ownerHeaders())->assertStatus($expected);

        $this->assertSame($expected === 204 ? 0 : 1, DB::table('crm_campaigns')->where('id', $campaignId)->count());
        $this->assertSame($recipientStatus === null ? 0 : 1, DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->count());
        if ($expected === 409) {
            $this->assertNotEmpty($response->json('message'));
        }
    }

    public function test_cancelling_a_scheduled_campaign_back_to_draft_removes_any_recipients_and_makes_it_deletable(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->addDay(), 'recipients_count' => 1]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');
        $sendingId = $this->insertCampaign(['status' => 'SENDING']);
        $this->insertRecipient($sendingId, $this->contactId, 'ada@example.com');

        $this->crm('POST', "campaigns/$campaignId/cancel", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->crm('POST', "campaigns/$sendingId/cancel", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'CANCELLED');

        $this->assertSame(0, DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->count());
        $this->assertSame(1, DB::table('crm_campaign_recipients')->where('campaign_id', $sendingId)->count());
        $this->crm('DELETE', "campaigns/$campaignId", [], $this->ownerHeaders())->assertNoContent();
        $this->crm('DELETE', "campaigns/$sendingId", [], $this->ownerHeaders())->assertStatus(409);
    }

    public static function cancellations(): array
    {
        return [
            'scheduled goes back to draft' => ['SCHEDULED', 200, 'DRAFT'],
            'pending review goes back to draft' => ['PENDING_REVIEW', 200, 'DRAFT'],
            'sending is cancelled' => ['SENDING', 200, 'CANCELLED'],
            'stopped by the health guard is final' => ['PAUSED', 409, 'PAUSED'],
            'draft cannot be cancelled' => ['DRAFT', 409, 'DRAFT'],
            'sent cannot be cancelled' => ['SENT', 409, 'SENT'],
            'cancelled cannot be cancelled again' => ['CANCELLED', 409, 'CANCELLED'],
            'rejected cannot be cancelled' => ['REJECTED', 409, 'REJECTED'],
        ];
    }

    #[DataProvider('cancellations')]
    public function test_cancelling_moves_a_campaign_to_the_right_status(string $from, int $expectedStatus, string $to): void
    {
        $campaignId = $this->insertCampaign(['status' => $from, 'scheduled_at' => now()->addDay(), 'status_reason' => 'UNTRUSTED_ACCOUNT']);

        $response = $this->crm('POST', "campaigns/$campaignId/cancel", [], $this->ownerHeaders())->assertStatus($expectedStatus);

        $campaign = $this->campaign($campaignId);
        $this->assertSame($to, $campaign->status);
        if ($to === 'DRAFT' && $expectedStatus === 200) {
            $response->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.is_editable', true);
            $this->assertNull($campaign->scheduled_at);
            $this->assertNull($campaign->status_reason);
        }
    }

    public function test_cancelling_an_automations_campaign_waiting_for_review_cancels_it_for_good_while_a_manual_one_returns_to_draft(): void
    {
        $automationId = DB::table('crm_automations')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'trigger' => 'WIN_BACK',
            'is_enabled' => true,
            'enabled_at' => now()->subDay(),
            'subject' => 'We miss you',
            'body' => '<p>Come back</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $waiting = ['status' => 'PENDING_REVIEW', 'status_reason' => 'UNTRUSTED_ACCOUNT', 'recipients_count' => 1];
        $automatedId = $this->insertCampaign([...$waiting, 'automation_id' => $automationId, 'automation_run_key' => 'date:'.now()->toDateString()]);
        $manualId = $this->insertCampaign($waiting);
        $this->insertRecipient($automatedId, $this->contactId, 'ada@example.com');
        $this->insertRecipient($manualId, $this->contactId, 'ada@example.com');

        $this->crm('POST', "campaigns/$automatedId/cancel", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED')
            ->assertJsonPath('data.is_editable', false);
        $this->crm('POST', "campaigns/$manualId/cancel", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.is_editable', true);

        $automated = $this->campaign($automatedId);
        $this->assertSame(['CANCELLED', null, null], [$automated->status, $automated->status_reason, $automated->scheduled_at]);
        $this->assertSame('DRAFT', $this->campaign($manualId)->status);
        $this->assertSame(['SKIPPED'], DB::table('crm_campaign_recipients')->where('campaign_id', $automatedId)->pluck('status')->all());
        $this->assertSame(0, DB::table('crm_campaign_recipients')->where('campaign_id', $manualId)->count());
        $this->crm('POST', "campaigns/$automatedId/cancel", [], $this->ownerHeaders())->assertStatus(409);
    }

    public static function refusedSends(): array
    {
        return [
            'account not verified' => ['unverified'],
            'saas account not manually verified' => ['not_manually_verified'],
            'nobody mailable in the segment' => ['no_recipients'],
            'more recipients than the tier allows' => ['too_many_recipients'],
            'links when the tier disallows them' => ['links'],
            'no subject' => ['no_subject'],
            'body with no text' => ['empty_body'],
            'saved segment was deleted' => ['segment_deleted'],
        ];
    }

    #[DataProvider('refusedSends')]
    public function test_the_send_gate_refuses_to_send_or_schedule(string $case): void
    {
        Bus::fake();
        $this->insertSubscribedContact('grace@example.com');
        $campaign = [];

        switch ($case) {
            case 'unverified':
                DB::table('accounts')->where('id', $this->accountId)->update(['account_verified_at' => null]);
                break;
            case 'not_manually_verified':
                config(['app.saas_mode_enabled' => true]);
                DB::table('accounts')->where('id', $this->accountId)->update(['is_manually_verified' => false]);
                break;
            case 'no_recipients':
                DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->update(['email_marketing_status' => 'UNSUBSCRIBED']);
                break;
            case 'too_many_recipients':
                $this->useTier(['max_recipients_per_message' => 1]);
                break;
            case 'links':
                $this->useTier(['links_allowed' => false]);
                $campaign = ['body' => '<p>Buy at <a href="https://example.com/tickets">our site</a></p>'];
                break;
            case 'no_subject':
                $campaign = ['subject' => '  '];
                break;
            case 'empty_body':
                $campaign = ['body' => '<p> </p><br>'];
                break;
            case 'segment_deleted':
                $campaign = ['segment_id' => 999999999];
                break;
        }

        $campaignId = $this->insertCampaign($campaign);
        $before = $this->crmState();

        $now = $this->crm('POST', "campaigns/$campaignId/send", [], $this->ownerHeaders());
        $later = $this->crm('POST', "campaigns/$campaignId/send", ['scheduled_at' => now()->addDay()->toDateTimeString()], $this->ownerHeaders());

        $this->assertSame([422, 422], [$now->getStatusCode(), $later->getStatusCode()], (string) $now->getContent());
        $this->assertNotEmpty($now->json('message'));
        $this->assertSame($before, $this->crmState());
        Bus::assertNothingDispatched();
    }

    public static function eventsThatCannotOfferEarlyAccess(): array
    {
        return [
            'not published' => [['status' => 'DRAFT'], 'Publish the event before sending people an early access link.'],
            'deleted' => [['status' => 'LIVE', 'deleted_at' => '2026-01-01 00:00:00'], 'The event linked to this campaign no longer exists.'],
        ];
    }

    #[DataProvider('eventsThatCannotOfferEarlyAccess')]
    public function test_an_early_access_campaign_for_an_event_that_cannot_offer_it_is_refused_before_review_or_scheduling(array $event, string $expectedReason): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        $this->insertSubscribedContact('grace@example.com');
        $campaignId = $this->insertCampaign(['event_id' => $this->eventId, 'early_access_product_ids' => json_encode([$this->productId])]);
        DB::table('events')->where('id', $this->eventId)->update($event);
        $before = $this->crmState();

        $this->crm('GET', "campaigns/$campaignId/send-check", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.blocked_reason', $expectedReason);
        $this->crm('POST', "campaigns/$campaignId/send", [], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', $expectedReason);
        $this->crm('POST', "campaigns/$campaignId/send", ['scheduled_at' => now()->addDay()->toDateTimeString()], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', $expectedReason);

        $this->assertSame($before, $this->crmState());
        Bus::assertNothingDispatched();
    }

    public function test_a_test_email_the_mail_service_would_not_take_is_reported_instead_of_crashing(): void
    {
        app('mail.manager')->extend('down', static fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection could not be established with host "smtp.example.test:587"');
            }

            public function __toString(): string
            {
                return 'down';
            }
        });
        config(['mail.mailers.down' => ['transport' => 'down'], 'mail.campaigns.mailer' => 'down']);

        $this->crm('POST', "campaigns/{$this->campaignId}/test", [], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', 'The test email couldn\'t be sent. Check your mail settings or try again in a moment.');
    }

    public function test_the_daily_cap_only_counts_campaigns_started_in_the_last_day_by_this_account(): void
    {
        Bus::fake();
        $this->useTier(['max_messages_per_24h' => 1]);
        $this->insertCampaign(['status' => 'SENT', 'launched_at' => now()->subHours(25), 'updated_at' => now()]);
        $this->insertCampaign(['status' => 'DRAFT']);
        $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->addDay()]);
        [, $otherAccountId] = $this->createAccountOwner();
        $this->insertCampaign(['status' => 'SENT', 'launched_at' => now()], $this->createOrganizer($otherAccountId), $otherAccountId);

        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'SENDING');
        $this->crm('POST', 'campaigns/'.$this->insertCampaign().'/send', [], $this->ownerHeaders())->assertStatus(422);
    }

    public function test_the_daily_cap_blocks_sending_now_but_not_scheduling_for_later(): void
    {
        Bus::fake();
        $this->useTier(['max_messages_per_24h' => 2]);
        $this->insertCampaign(['status' => 'SENT', 'launched_at' => now()->subHours(2)]);
        $this->insertCampaign(['status' => 'SENDING', 'launched_at' => now()->subHours(3)], $this->otherOrganizerId);

        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have reached your limit of campaigns for today. Please try again tomorrow.');
        $this->crm('POST', "campaigns/{$this->campaignId}/send", ['scheduled_at' => now()->addDays(2)->toDateTimeString()], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'SCHEDULED');
        Bus::assertNothingDispatched();
    }

    public function test_automation_campaigns_neither_count_toward_nor_are_blocked_by_the_daily_cap(): void
    {
        Bus::fake();
        $this->useTier(['max_messages_per_24h' => 1]);
        $this->insertCampaign(['status' => 'SENT', 'launched_at' => now()->subHour(), 'automation_id' => 999999, 'automation_run_key' => 'date:a']);

        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'SENDING');

        $automationCampaignId = $this->insertCampaign(['automation_id' => 999999, 'automation_run_key' => 'date:b']);
        $this->assertSame('SENDING', app(CampaignLaunchService::class)->launch($this->campaignRepository()->findById($automationCampaignId))->value);
    }

    public function test_the_daily_cap_counts_a_campaign_that_started_sending_and_was_then_cancelled(): void
    {
        Bus::fake();
        $this->useTier(['max_messages_per_24h' => 2]);
        $this->insertCampaign(['status' => 'CANCELLED']);
        $this->insertCampaign(['status' => 'CANCELLED', 'sent_at' => now()->subHours(30), 'launched_at' => now()->subHours(25), 'updated_at' => now()]);
        $this->insertCampaign(['status' => 'REJECTED']);

        $this->crm('POST', 'campaigns/'.$this->insertCampaign().'/send', [], $this->ownerHeaders())->assertOk();

        $this->insertCampaign(['status' => 'CANCELLED', 'sent_at' => now()->subHours(3), 'launched_at' => now()->subHours(3)]);

        $this->crm('POST', 'campaigns/'.$this->insertCampaign().'/send', [], $this->ownerHeaders())->assertStatus(422);
    }

    public static function saasReviews(): array
    {
        return [
            'untrusted tier' => ['Untrusted', false, 'PENDING_REVIEW', 'UNTRUSTED_ACCOUNT'],
            'untrusted tier with a campaign already sent' => ['Untrusted', true, 'PENDING_REVIEW', 'UNTRUSTED_ACCOUNT'],
            'no tier assigned' => [null, true, 'PENDING_REVIEW', 'UNTRUSTED_ACCOUNT'],
            'trusted tier sending its first campaign' => ['Trusted', false, 'PENDING_REVIEW', 'FIRST_CAMPAIGN'],
            'trusted tier with a campaign already sent' => ['Trusted', true, 'SENDING', null],
        ];
    }

    #[DataProvider('saasReviews')]
    public function test_in_saas_mode_untrusted_accounts_and_first_campaigns_wait_for_review(?string $tier, bool $hasSentBefore, string $expectedStatus, ?string $reason): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        DB::table('accounts')->where('id', $this->accountId)->update(['account_messaging_tier_id' => $tier === null ? null : $this->messagingTierId($tier)]);
        if ($hasSentBefore) {
            $this->insertCampaign(['status' => 'SENT', 'updated_at' => now()->subDays(3)]);
        }

        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', $expectedStatus)
            ->assertJsonPath('data.status_reason', $reason);

        if ($expectedStatus === 'PENDING_REVIEW') {
            Bus::assertDispatched(NotifyCampaignPendingReviewJob::class);
            Bus::assertNotDispatched(DispatchCampaignJob::class);
            $this->assertSame(['PENDING'], DB::table('crm_campaign_recipients')->where('campaign_id', $this->campaignId)->pluck('status')->all());
            $this->assertCount(0, $this->sentMessages());

            return;
        }

        Bus::assertDispatched(DispatchCampaignJob::class, fn (DispatchCampaignJob $job) => $job->campaignId === $this->campaignId);
        Bus::assertNotDispatched(NotifyCampaignPendingReviewJob::class);
    }

    public function test_a_scheduled_campaign_that_needs_review_is_reviewed_now_and_sent_at_its_time(): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        $this->freezeSecond();
        $scheduledAt = now()->addDay()->startOfSecond();

        $this->crm('POST', "campaigns/{$this->campaignId}/send", ['scheduled_at' => $scheduledAt->toDateTimeString()], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING_REVIEW')
            ->assertJsonPath('data.status_reason', 'FIRST_CAMPAIGN');
        $this->assertSame($scheduledAt->toDateTimeString(), $this->campaign($this->campaignId)->scheduled_at);
        $this->assertNull($this->campaign($this->campaignId)->launched_at);
        $this->assertSame(['PENDING'], DB::table('crm_campaign_recipients')->where('campaign_id', $this->campaignId)->pluck('status')->all());
        Bus::assertDispatched(NotifyCampaignPendingReviewJob::class);

        $campaign = $this->campaignRepository()->findById($this->campaignId);
        $this->assertTrue(app(CampaignLaunchService::class)->approve($campaign, Carbon::parse($campaign->getUpdatedAt())));

        $approved = $this->campaign($this->campaignId);
        $this->assertSame(['SCHEDULED', $scheduledAt->toDateTimeString()], [$approved->status, $approved->scheduled_at]);
        $this->assertNotNull($approved->approved_at);
        Bus::assertNotDispatched(DispatchCampaignJob::class);

        app(CampaignLaunchService::class)->launchScheduled($this->campaignId, now());
        $this->assertSame('SCHEDULED', $this->campaign($this->campaignId)->status);

        app(CampaignLaunchService::class)->launchScheduled($this->campaignId, $scheduledAt);

        $sent = $this->campaign($this->campaignId);
        $this->assertSame(['SENDING', null], [$sent->status, $sent->scheduled_at]);
        $this->assertNotNull($sent->launched_at);
        Bus::assertDispatched(DispatchCampaignJob::class, fn (DispatchCampaignJob $job) => $job->campaignId === $this->campaignId);
    }

    public function test_a_scheduled_campaign_approved_after_its_time_is_sent_straight_away(): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        $this->crm('POST', "campaigns/{$this->campaignId}/send", ['scheduled_at' => now()->addHour()->toDateTimeString()], $this->ownerHeaders())
            ->assertJsonPath('data.status', 'PENDING_REVIEW');
        $campaign = $this->campaignRepository()->findById($this->campaignId);

        $this->travel(2)->hours();
        $this->assertTrue(app(CampaignLaunchService::class)->approve($campaign, Carbon::parse($campaign->getUpdatedAt())));

        $this->assertSame(['SENDING', null], [$this->campaign($this->campaignId)->status, $this->campaign($this->campaignId)->scheduled_at]);
        Bus::assertDispatched(DispatchCampaignJob::class, fn (DispatchCampaignJob $job) => $job->campaignId === $this->campaignId);
    }

    public function test_moving_an_approved_scheduled_campaign_back_to_draft_withdraws_it_and_needs_a_new_review(): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        $this->crm('POST', "campaigns/{$this->campaignId}/send", ['scheduled_at' => now()->addDay()->toDateTimeString()], $this->ownerHeaders());
        $campaign = $this->campaignRepository()->findById($this->campaignId);
        app(CampaignLaunchService::class)->approve($campaign, Carbon::parse($campaign->getUpdatedAt()));

        $this->crm('POST', "campaigns/{$this->campaignId}/cancel", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'DRAFT');

        $this->assertNull($this->campaign($this->campaignId)->approved_at);
        $this->assertSame(0, DB::table('crm_campaign_recipients')->where('campaign_id', $this->campaignId)->count());
        $this->crm('POST', "campaigns/{$this->campaignId}/send", ['scheduled_at' => now()->addDay()->toDateTimeString()], $this->ownerHeaders())
            ->assertJsonPath('data.status', 'PENDING_REVIEW');
    }

    public function test_an_account_whose_messaging_tier_was_deleted_is_treated_as_untrusted(): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        $this->insertCampaign(['status' => 'SENT', 'updated_at' => now()->subDays(3)]);
        $this->useTier(['deleted_at' => now()]);

        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING_REVIEW')
            ->assertJsonPath('data.status_reason', CampaignSendGate::REVIEW_REASON_UNTRUSTED_ACCOUNT);

        Bus::assertDispatched(NotifyCampaignPendingReviewJob::class);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_outside_saas_mode_an_untrusted_first_campaign_sends_without_review(): void
    {
        Bus::fake();
        DB::table('accounts')->where('id', $this->accountId)->update(['account_messaging_tier_id' => null, 'is_manually_verified' => false]);

        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'SENDING');

        Bus::assertDispatched(DispatchCampaignJob::class);
        Bus::assertNotDispatched(NotifyCampaignPendingReviewJob::class);
    }

    public function test_the_review_notification_goes_to_the_platform_support_address(): void
    {
        Mail::fake();
        config(['app.platform_support_email' => 'support@platform.test']);
        $campaignId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'name' => 'Needs a look']);

        app()->call([new NotifyCampaignPendingReviewJob($campaignId, 12, 'FIRST_CAMPAIGN'), 'handle']);

        Mail::assertQueued(CampaignPendingReviewMail::class, static fn (CampaignPendingReviewMail $mail) => $mail->hasTo('support@platform.test'));
        Mail::assertQueuedCount(1);
    }

    public static function sendChecks(): array
    {
        return [
            'self-hosted sends straight away' => [false, 'Untrusted', false, ['requires_review' => false, 'review_reason' => null, 'blocked_reason' => null, 'warnings' => [], 'early_access_tickets' => []]],
            'saas untrusted account is reviewed' => [true, 'Untrusted', true, ['requires_review' => true, 'review_reason' => 'UNTRUSTED_ACCOUNT', 'blocked_reason' => null, 'warnings' => [], 'early_access_tickets' => []]],
            'saas first campaign is reviewed' => [true, 'Trusted', false, ['requires_review' => true, 'review_reason' => 'FIRST_CAMPAIGN', 'blocked_reason' => null, 'warnings' => [], 'early_access_tickets' => []]],
            'saas trusted account that sent before is not reviewed' => [true, 'Trusted', true, ['requires_review' => false, 'review_reason' => null, 'blocked_reason' => null, 'warnings' => [], 'early_access_tickets' => []]],
        ];
    }

    #[DataProvider('sendChecks')]
    public function test_the_send_check_says_whether_a_campaign_will_be_reviewed_without_changing_anything(bool $isSaas, string $tier, bool $hasSentBefore, array $expected): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => $isSaas]);
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId($tier), 'is_manually_verified' => true]);
        if ($hasSentBefore) {
            $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subDays(3), 'launched_at' => now()->subDays(3)]);
        }
        $before = $this->crmState();

        $this->crm('GET', "campaigns/{$this->campaignId}/send-check", [], $this->ownerHeaders())
            ->assertOk()
            ->assertExactJson(['data' => ['mailable_count' => 1, ...$expected]]);

        $this->assertSame($before, $this->crmState());
        Bus::assertNothingDispatched();
    }

    public function test_the_send_check_explains_why_a_campaign_cannot_be_sent(): void
    {
        DB::table('accounts')->where('id', $this->accountId)->update(['account_verified_at' => null]);

        $this->crm('GET', "campaigns/{$this->campaignId}/send-check", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.requires_review', false)
            ->assertJsonPath('data.blocked_reason', 'Confirm your account\'s email address before sending campaigns. Check your inbox for the confirmation email.');
    }

    public function test_the_send_check_is_scoped_to_the_organizer_and_hides_spend_campaigns_from_members_without_view_financials(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spendCampaignId = $this->insertCampaign(['segment_definition' => json_encode(['match' => 'all', 'rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 1, 'currency' => 'USD']]])]);

        $this->crm('GET', "campaigns/{$this->otherCampaignId}/send-check", [], $this->ownerHeaders())->assertNotFound();
        $this->crm('GET', "campaigns/{$this->otherCampaignId}/send-check", [], $this->ownerHeaders(), $this->otherOrganizerId)->assertOk();
        $this->crm('GET', "campaigns/$spendCampaignId/send-check", [], $this->memberHeaders($member))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
        $this->crm('GET', "campaigns/$spendCampaignId/send-check", [], $this->ownerHeaders())->assertOk();
    }

    public function test_a_campaign_is_scheduled_in_the_organizers_timezone(): void
    {
        Bus::fake();
        DB::table('organizers')->where('id', $this->organizerId)->update(['timezone' => 'America/New_York']);

        $this->crm('POST', "campaigns/{$this->campaignId}/send", ['scheduled_at' => '2099-01-15 12:00:00'], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'SCHEDULED')
            ->assertJsonPath('data.is_editable', false);

        $this->assertSame('2099-01-15 17:00:00', $this->campaign($this->campaignId)->scheduled_at);
        Bus::assertNothingDispatched();

        $this->crm('POST', "campaigns/{$this->campaignId}/cancel", [], $this->ownerHeaders())->assertOk();
        $this->crm('POST', "campaigns/{$this->campaignId}/send", ['scheduled_at' => '2099-02-01 12:00:00'], $this->ownerHeaders())->assertOk();
        $this->assertSame('2099-02-01 17:00:00', $this->campaign($this->campaignId)->scheduled_at);
    }

    public static function invalidSchedules(): array
    {
        return [
            'in the past' => ['2020-01-01 12:00:00'],
            'not a date' => ['next-ish'],
        ];
    }

    #[DataProvider('invalidSchedules')]
    public function test_a_campaign_cannot_be_scheduled_in_the_past(string $scheduledAt): void
    {
        Bus::fake();

        $this->crm('POST', "campaigns/{$this->campaignId}/send", ['scheduled_at' => $scheduledAt], $this->ownerHeaders())->assertStatus(422);

        $campaign = $this->campaign($this->campaignId);
        $this->assertSame(['DRAFT', null], [$campaign->status, $campaign->scheduled_at]);
        Bus::assertNothingDispatched();
    }

    public function test_sending_the_same_campaign_twice_only_dispatches_once(): void
    {
        Bus::fake();

        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())->assertOk();
        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())->assertStatus(422);

        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
    }

    public function test_a_test_send_goes_only_to_the_requesting_user_without_an_unsubscribe_link(): void
    {
        Bus::fake();
        $member = $this->addAccountUser($this->accountId);
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update([
            'subject' => 'Big news',
            'body' => '<p>Hello there</p><script>alert("x")</script>',
        ]);

        $this->crm('POST', "campaigns/{$this->campaignId}/test", [], $this->memberHeaders($member))->assertOk();

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame([$member->email], array_map(static fn ($address) => $address->getAddress(), $email->getTo()));
        $this->assertSame([], $email->getCc());
        $this->assertSame([], $email->getBcc());
        $this->assertSame('[Test] Big news', $email->getSubject());
        $this->assertFalse($email->getHeaders()->has('List-Unsubscribe'));
        $this->assertFalse($email->getHeaders()->has('List-Unsubscribe-Post'));
        $this->assertStringContainsString('Hello there', $email->getHtmlBody());
        $this->assertStringContainsString('Test Organizer', $email->getHtmlBody());
        $this->assertStringNotContainsString('<script', $email->getHtmlBody());
        $this->assertStringNotContainsString('alert(', $email->getHtmlBody());
        $this->assertStringNotContainsString('unsubscribe/', $email->getHtmlBody());

        $this->assertSame('DRAFT', $this->campaign($this->campaignId)->status);
        $this->assertSame(0, DB::table('crm_campaign_recipients')->count());
        Bus::assertNotDispatched(SendCampaignEmailJob::class);
    }

    public function test_a_test_send_and_the_preview_greet_the_signed_in_user_by_name(): void
    {
        $member = $this->addAccountUser($this->accountId);
        DB::table('users')->where('id', $member->id)->update(['first_name' => 'Grace', 'last_name' => 'Hopper']);
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update([
            'subject' => 'Hi {{ first_name }}',
            'body' => '<p>Dear {{ first_name }} {{ last_name }}</p>',
        ]);

        $this->crm('POST', "campaigns/{$this->campaignId}/test", [], $this->memberHeaders($member))->assertOk();
        $html = $this->crm('GET', "campaigns/{$this->campaignId}/preview", [], $this->memberHeaders($member))
            ->assertOk()
            ->assertJsonPath('data.subject', 'Hi Grace')
            ->json('data.html');
        $draft = $this->crm('POST', 'campaigns/preview', ['subject' => 'Hi {{ first_name }}', 'body' => '<p>Hello {{ first_name | default: &quot;there&quot; }}</p>'], $this->memberHeaders($member))
            ->assertOk()
            ->assertJsonPath('data.subject', 'Hi Grace')
            ->json('data.html');

        $email = $this->sentMessages()->first()->getOriginalMessage();
        $this->assertSame('[Test] Hi Grace', $email->getSubject());
        $this->assertStringContainsString('Dear Grace Hopper', $email->getHtmlBody());
        $this->assertStringContainsString('Dear Grace Hopper', $html);
        $this->assertStringContainsString('Hello Grace', $draft);
    }

    public static function accountsThatMayNotSend(): array
    {
        return [
            'account not verified' => [false, ['account_verified_at' => null], 422],
            'account not verified in saas mode' => [true, ['account_verified_at' => null], 422],
            'saas account not manually verified' => [true, ['is_manually_verified' => false], 422],
            'self-hosted account not manually verified' => [false, ['is_manually_verified' => false], 200],
        ];
    }

    #[DataProvider('accountsThatMayNotSend')]
    public function test_a_test_send_needs_an_account_that_may_send_campaigns(bool $saasMode, array $account, int $expected): void
    {
        config(['app.saas_mode_enabled' => $saasMode]);
        DB::table('accounts')->where('id', $this->accountId)->update($account);

        $response = $this->crm('POST', "campaigns/{$this->campaignId}/test", [], $this->ownerHeaders())->assertStatus($expected);

        $this->assertCount($expected === 200 ? 1 : 0, $this->sentMessages());
        if ($expected === 422) {
            $this->assertNotEmpty($response->json('message'));
        }
    }

    public function test_a_test_send_needs_a_subject_and_a_message(): void
    {
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['subject' => null]);

        $this->crm('POST', "campaigns/{$this->campaignId}/test", [], $this->ownerHeaders())->assertStatus(422);

        $this->assertCount(0, $this->sentMessages());
    }

    public function test_the_preview_renders_the_purified_message_without_sending_anything(): void
    {
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['body' => '<p>Preview me</p><script>alert("x")</script>']);

        $html = $this->crm('GET', "campaigns/{$this->campaignId}/preview", [], $this->ownerHeaders())->assertOk()->json('data.html');

        $this->assertStringContainsString('Preview me', $html);
        $this->assertStringContainsString('Test Organizer', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('unsubscribe/', $html);
        $this->assertCount(0, $this->sentMessages());
    }

    public function test_only_a_superadmin_can_see_and_review_pending_campaigns(): void
    {
        Bus::fake();
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN']);
        $before = $this->crmState();

        foreach ([[], $this->ownerHeaders(), $this->memberHeaders($this->addAccountUser($this->accountId, Role::ADMIN))] as $index => $headers) {
            $expected = $headers === [] ? 401 : 403;
            $this->assertSame($expected, $this->json('GET', '/admin/crm/campaigns/pending-review', [], $headers)->getStatusCode(), "list $index");
            $this->assertSame($expected, $this->json('POST', "/admin/crm/campaigns/$pendingId/approve", $this->reviewed($pendingId), $headers)->getStatusCode(), "approve $index");
            $this->assertSame($expected, $this->json('POST', "/admin/crm/campaigns/$pendingId/reject", [], $headers)->getStatusCode(), "reject $index");
        }

        $this->assertSame($before, $this->crmState());
        Bus::assertNothingDispatched();
    }

    public function test_a_superadmin_lists_pending_campaigns_with_their_audience_size_and_a_safe_preview(): void
    {
        $pendingId = $this->insertCampaign([
            'status' => 'PENDING_REVIEW',
            'status_reason' => 'FIRST_CAMPAIGN',
            'name' => 'Waiting',
            'body' => '<p>Review me</p><script>alert("x")</script>',
            'recipients_count' => 2,
        ]);

        $listed = collect($this->json('GET', '/admin/crm/campaigns/pending-review', [], $this->superAdminHeaders())->assertOk()->json('data'))->keyBy('id');

        $this->assertArrayNotHasKey($this->campaignId, $listed);
        $this->assertSame(
            ['Waiting', 'FIRST_CAMPAIGN', $this->accountId, $this->organizerId, 'Test Organizer', 2],
            array_map(static fn (string $key) => $listed[$pendingId][$key], ['name', 'status_reason', 'account_id', 'organizer_id', 'organizer_name', 'recipients_count']),
        );
        $this->assertStringContainsString('Review me', $listed[$pendingId]['preview_html']);
        $this->assertStringNotContainsString('<script', $listed[$pendingId]['preview_html']);
    }

    public function test_the_review_queue_says_how_many_recipients_only_opted_in_through_an_import(): void
    {
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'RECENT_IMPORT', 'recipients_count' => 2]);
        $importedId = $this->insertContact('imported@example.com', ['email_marketing_status' => 'SUBSCRIBED', 'source' => 'IMPORT']);
        DB::table('crm_consent_events')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $importedId,
            'email' => 'imported@example.com',
            'action' => 'OPT_IN',
            'source_type' => 'IMPORT',
            'source_id' => 1,
            'occurred_at' => now()->subHour(),
        ]);
        $this->insertRecipient($pendingId, $importedId, 'imported@example.com');
        $this->insertRecipient($pendingId, $this->contactId, (string) DB::table('crm_contacts')->where('id', $this->contactId)->value('email'));
        DB::table('crm_consent_events')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $this->contactId,
            'email' => (string) DB::table('crm_contacts')->where('id', $this->contactId)->value('email'),
            'action' => 'OPT_IN',
            'source_type' => 'ORDER',
            'source_id' => 1,
            'occurred_at' => now()->subDay(),
        ]);

        $listed = collect($this->json('GET', '/admin/crm/campaigns/pending-review', [], $this->superAdminHeaders())->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(1, $listed[$pendingId]['import_only_recipients_count']);
        $this->assertNotNull($listed[$pendingId]['account_created_at']);
    }

    public static function deletedLinkedRecords(): array
    {
        return [
            'promo code deleted' => ['promo_code', true],
            'event deleted' => ['event', false],
        ];
    }

    #[DataProvider('deletedLinkedRecords')]
    public function test_the_pending_review_list_survives_a_campaign_whose_linked_record_was_deleted(string $deleted, bool $linksToEvent): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $promoCodeId = DB::table('promo_codes')->insertGetId(['event_id' => $this->eventId, 'code' => 'early-existing', 'discount' => 0, 'discount_type' => 'NONE', 'created_at' => now(), 'updated_at' => now()]);
        $pendingId = $this->insertCampaign([
            'status' => 'PENDING_REVIEW',
            'status_reason' => 'FIRST_CAMPAIGN',
            'body' => '<p>Review me</p>',
            'event_id' => $this->eventId,
            'promo_code_id' => $promoCodeId,
        ]);
        $healthyId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'body' => '<p>Healthy</p>'], $this->otherOrganizerId);

        match ($deleted) {
            'promo_code' => DB::table('promo_codes')->where('id', $promoCodeId)->update(['deleted_at' => now()]),
            'event' => DB::table('events')->where('id', $this->eventId)->update(['deleted_at' => now()]),
        };

        $listed = collect($this->json('GET', '/admin/crm/campaigns/pending-review', [], $this->superAdminHeaders())->assertOk()->json('data'))->keyBy('id');

        $this->assertStringContainsString('Healthy', $listed[$healthyId]['preview_html']);
        $this->assertStringContainsString('Review me', $listed[$pendingId]['preview_html']);
        $this->assertStringNotContainsString('promo_code', $listed[$pendingId]['preview_html']);
        $this->assertSame($linksToEvent, str_contains($listed[$pendingId]['preview_html'], '/event/'.$this->eventId.'/'));

        $this->json('POST', "/admin/crm/campaigns/$pendingId/reject", [], $this->superAdminHeaders())->assertOk();
        $this->assertSame('REJECTED', $this->campaign($pendingId)->status);
    }

    public function test_the_pending_review_list_leaves_out_campaigns_of_a_deleted_organizer(): void
    {
        $orphanedId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'body' => '<p>Orphaned</p>']);
        $healthyId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'body' => '<p>Healthy</p>'], $this->otherOrganizerId);
        DB::table('organizers')->where('id', $this->organizerId)->update(['deleted_at' => now()]);

        $listed = collect($this->json('GET', '/admin/crm/campaigns/pending-review', [], $this->superAdminHeaders())->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($listed->contains($healthyId));
        $this->assertFalse($listed->contains($orphanedId));
    }

    public function test_reviewing_a_campaign_that_was_deleted_is_not_found(): void
    {
        Bus::fake();
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN']);
        DB::table('crm_campaigns')->where('id', $pendingId)->delete();

        $this->json('POST', "/admin/crm/campaigns/$pendingId/approve", $this->reviewed($pendingId), $this->superAdminHeaders())->assertNotFound();
        $this->json('POST', "/admin/crm/campaigns/$pendingId/reject", [], $this->superAdminHeaders())->assertNotFound();

        Bus::assertNothingDispatched();
    }

    public function test_a_rejection_note_is_stored_as_plain_text_shown_to_the_organizer_and_emailed_to_the_campaigns_creator(): void
    {
        Mail::fake();
        $creator = $this->addAccountUser($this->accountId);
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'subject' => 'Autumn <i>news</i>', 'created_by_user_id' => $creator->id]);

        $this->json('POST', "/admin/crm/campaigns/$pendingId/reject", ['note' => '  Remove the <a href="https://casino.example">casino</a> link & <script>x</script>resend. '], $this->superAdminHeaders())
            ->assertOk();

        $this->assertSame(['REJECTED', 'Remove the casino link & xresend.'], [$this->campaign($pendingId)->status, $this->campaign($pendingId)->review_note]);
        $this->crm('GET', "campaigns/$pendingId", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.review_note', 'Remove the casino link & xresend.');

        Mail::assertQueued(CampaignRejectedMail::class, 1);
        Mail::assertQueued(CampaignRejectedMail::class, function (CampaignRejectedMail $mail) use ($creator, $pendingId): bool {
            $html = $mail->render();

            return $mail->hasTo($creator->email)
                && ! $mail->hasTo($this->owner->email)
                && str_contains($html, 'Remove the casino link &amp; xresend.')
                && str_contains($html, 'Autumn &lt;i&gt;news&lt;/i&gt;')
                && str_contains($html, "/manage/organizer/{$this->organizerId}/audience/campaigns/$pendingId")
                && ! str_contains($html, '<script');
        });
    }

    public function test_a_scheduled_campaign_that_is_not_approved_no_longer_carries_its_send_time(): void
    {
        Mail::fake();
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'scheduled_at' => now()->addDay()]);

        $this->json('POST', "/admin/crm/campaigns/$pendingId/reject", [], $this->superAdminHeaders())->assertOk();

        $this->crm('GET', "campaigns/$pendingId", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'REJECTED')
            ->assertJsonPath('data.scheduled_at', null);
    }

    public function test_a_rejection_without_a_note_of_an_automated_campaign_emails_the_account_owner(): void
    {
        Mail::fake();
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'automation_id' => 999999, 'automation_run_key' => 'date:today', 'review_note' => 'An earlier note']);

        $this->json('POST', "/admin/crm/campaigns/$pendingId/reject", [], $this->superAdminHeaders())->assertOk();

        $this->assertSame(['CANCELLED', null], [$this->campaign($pendingId)->status, $this->campaign($pendingId)->review_note]);
        Mail::assertQueued(CampaignRejectedMail::class, fn (CampaignRejectedMail $mail): bool => $mail->hasTo($this->owner->email)
            && str_contains($mail->render(), 'This email was sent by one of your automations.')
            && str_contains($mail->render(), "/manage/organizer/{$this->organizerId}/audience/automations\"")
            && str_contains($mail->render(), 'Open automations')
            && ! str_contains($mail->render(), "/audience/campaigns/$pendingId")
            && ! str_contains($mail->render(), 'You can edit the campaign and send it again'));
    }

    public function test_a_preview_names_the_sender_and_reply_address_the_delivered_email_will_carry(): void
    {
        config(['mail.from.name' => 'Ticket Desk', 'mail.campaigns.mailer' => null]);
        DB::table('organizers')->where('id', $this->organizerId)->update(['email' => 'hello@organizer.test']);
        $draft = ['subject' => 'Hi', 'body' => '<p>Hello</p>'];

        $this->crm('GET', "campaigns/{$this->campaignId}/preview", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.from_name', 'Ticket Desk')
            ->assertJsonPath('data.reply_to', 'hello@organizer.test');
        $this->crm('POST', 'campaigns/preview', $draft, $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.from_name', 'Ticket Desk')
            ->assertJsonPath('data.reply_to', 'hello@organizer.test');

        config(['mail.campaigns.mailer' => 'bulk', 'mail.mailers.bulk' => ['transport' => 'array', 'from' => ['address' => 'news@platform.test', 'name' => 'Platform News']]]);

        $this->crm('POST', 'campaigns/preview', $draft, $this->ownerHeaders())->assertOk()->assertJsonPath('data.from_name', 'Platform News');
        $this->crm('POST', "campaigns/{$this->campaignId}/test", [], $this->ownerHeaders())->assertOk();

        $from = app('mail.manager')->mailer('bulk')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getFrom()[0];
        $this->assertSame(['Platform News', 'news@platform.test'], [$from->getName(), $from->getAddress()]);
    }

    public function test_the_campaigns_list_says_how_long_after_its_time_a_scheduled_send_is_still_sent(): void
    {
        $this->crm('GET', 'campaigns', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('meta.scheduled_send_window_hours', CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS);
    }

    public function test_the_campaigns_list_says_up_front_when_the_account_cannot_send_yet(): void
    {
        $this->crm('GET', 'campaigns', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('meta.send_gate', ['blocked_reason' => null]);

        config(['app.saas_mode_enabled' => true, 'app.platform_support_email' => 'support@platform.test']);
        DB::table('accounts')->where('id', $this->accountId)->update(['is_manually_verified' => false]);
        $reason = 'To protect people from spam, we enable campaigns for each account by hand. Please contact us at support@platform.test.';

        $this->crm('GET', 'campaigns', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('meta.send_gate', ['blocked_reason' => $reason]);
        $this->crm('GET', "campaigns/{$this->campaignId}/send-check", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.blocked_reason', $reason);
    }

    public function test_an_account_waiting_to_be_enabled_is_never_pointed_at_a_placeholder_support_address(): void
    {
        config(['app.saas_mode_enabled' => true, 'app.platform_support_email' => 'support@example.com']);
        DB::table('accounts')->where('id', $this->accountId)->update(['is_manually_verified' => false]);

        $this->crm('GET', "campaigns/{$this->campaignId}/send-check", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.blocked_reason', 'To protect people from spam, we enable campaigns for each account by hand. Please contact us to get yours enabled.');
    }

    public static function installs(): array
    {
        return [
            'self-hosted' => [false, 'support@platform.test', 'This campaign has 2 recipients, which is more than can be sent to at once (1). Send to a smaller segment.', 'Your account can\'t send links yet. Remove the links to send this campaign.'],
            'platform' => [true, 'support@platform.test', 'This campaign has 2 recipients, which is more than your account can send to at once (1). Contact us at support@platform.test to raise the limit, or send to a smaller segment.', 'Your account can\'t send links yet. Remove the links, or contact us at support@platform.test to enable them.'],
            'platform that never set a support address' => [true, 'support@example.com', 'This campaign has 2 recipients, which is more than your account can send to at once (1). Contact us to raise the limit, or send to a smaller segment.', 'Your account can\'t send links yet. Remove the links, or contact us to enable them.'],
        ];
    }

    #[DataProvider('installs')]
    public function test_send_limits_only_point_at_a_support_contact_where_one_exists(bool $isSaas, string $supportEmail, string $tooMany, string $noLinks): void
    {
        config(['app.saas_mode_enabled' => $isSaas, 'app.platform_support_email' => $supportEmail]);
        DB::table('accounts')->where('id', $this->accountId)->update(['is_manually_verified' => true]);
        $this->insertSubscribedContact('extra@example.com');
        $mailable = DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->where('email_marketing_status', 'SUBSCRIBED')->count();
        $this->assertSame(2, $mailable);

        $this->useTier(['max_recipients_per_message' => 1]);
        $this->crm('GET', "campaigns/{$this->campaignId}/send-check", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.blocked_reason', $tooMany);

        $this->useTier(['links_allowed' => false]);
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['body' => '<p>Buy at <a href="https://example.com/tickets">our site</a></p>']);
        $this->crm('GET', "campaigns/{$this->campaignId}/send-check", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.blocked_reason', $noLinks);
    }

    public function test_a_campaign_says_whether_delivery_reports_can_arrive_on_this_install(): void
    {
        config(['services.postmark.webhook_secret' => null]);
        $this->crm('GET', "campaigns/{$this->campaignId}", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.delivery_reports_available', false);
        $this->crm('GET', 'campaigns', [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.0.delivery_reports_available', false);
        $textCampaignId = $this->insertCampaign(['channel' => 'SMS', 'subject' => null, 'body' => 'On sale now']);
        $this->crm('GET', "campaigns/$textCampaignId", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.delivery_reports_available', true);

        config(['services.postmark.webhook_secret' => 'secret']);
        $this->crm('GET', "campaigns/{$this->campaignId}", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.delivery_reports_available', true);
    }

    public function test_a_campaign_returned_to_draft_carries_a_reason_code_and_its_parameters(): void
    {
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['status_reason' => CampaignLaunchService::REASON_SCHEDULED_SEND_EXPIRED]);
        $this->crm('GET', "campaigns/{$this->campaignId}", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status_reason', 'SCHEDULED_SEND_EXPIRED')
            ->assertJsonPath('data.status_reason_params', ['hours' => CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS]);

        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['status_reason' => CampaignLaunchService::REASON_SCHEDULED_SEND_FAILED]);
        $this->crm('GET', "campaigns/{$this->campaignId}", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status_reason_params', null);
    }

    public function test_an_automated_campaign_stopped_mid_send_stays_in_the_list_while_runs_that_sent_nothing_stay_hidden(): void
    {
        $run = ['status' => 'CANCELLED', 'automation_id' => 999999];
        $stoppedId = $this->insertCampaign([...$run, 'automation_run_key' => 'date:stopped', 'sent_at' => now()->subHour()]);
        $withdrawnId = $this->insertCampaign([...$run, 'automation_run_key' => 'date:withdrawn']);
        $expiredId = $this->insertCampaign([...$run, 'automation_run_key' => 'date:expired', 'status_reason' => CampaignLaunchService::REASON_REVIEW_EXPIRED]);

        $response = $this->crm('GET', 'campaigns?source=automation', [], $this->ownerHeaders())->assertOk();

        $listed = array_column($response->json('data'), 'id');
        $this->assertContains($stoppedId, $listed);
        $this->assertNotContains($withdrawnId, $listed);
        $this->assertNotContains($expiredId, $listed);
        $this->assertSame(1, $response->json('meta.folders.automated'));
        $this->assertSame(1, $response->json('meta.folders.sent'));
    }

    public function test_the_review_queue_shows_a_text_campaign_as_recipients_will_get_it(): void
    {
        $organizerName = (string) DB::table('organizers')->where('id', $this->organizerId)->value('name');
        $pendingId = $this->insertCampaign([
            'status' => 'PENDING_REVIEW',
            'status_reason' => 'FIRST_CAMPAIGN',
            'channel' => 'SMS',
            'subject' => null,
            'event_id' => $this->eventId,
            'early_access_product_ids' => null,
            'body' => 'Hi {{ first_name | default: "there" }}, tickets for {{ event_title }}: {{ event_url }}',
        ]);
        DB::table('events')->where('id', $this->eventId)->update(['deleted_at' => now()]);

        $listed = collect($this->json('GET', '/admin/crm/campaigns/pending-review', [], $this->superAdminHeaders())->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(['SMS', null], [$listed[$pendingId]['channel'], $listed[$pendingId]['preview_html']]);
        $this->assertStringStartsWith($organizerName.': Hi there, tickets for', $listed[$pendingId]['preview_text']);
        $this->assertStringContainsString('Opt out: ', $listed[$pendingId]['preview_text']);
        $this->assertStringNotContainsString('{{', $listed[$pendingId]['preview_text']);
    }

    public function test_the_review_queue_says_who_a_campaign_reaches_and_what_approving_it_will_do(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        DB::table('organizers')->where('id', $this->organizerId)->update(['email' => 'replies@organizer.test']);
        $ticketTitles = DB::table('products')->where('event_id', $this->eventId)->orderBy('id')->pluck('title')->all();
        $earlyAccessId = $this->insertCampaign([
            'status' => 'PENDING_REVIEW',
            'status_reason' => 'FIRST_CAMPAIGN',
            'event_id' => $this->eventId,
            'early_access_product_ids' => json_encode(DB::table('products')->where('event_id', $this->eventId)->orderBy('id')->pluck('id')->all()),
            'segment_id' => $this->segmentId,
        ]);
        $everyoneId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN']);

        $listed = collect($this->json('GET', '/admin/crm/campaigns/pending-review', [], $this->superAdminHeaders())->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(
            [DB::table('crm_segments')->where('id', $this->segmentId)->value('name'), 'replies@organizer.test', 'Main Event', $ticketTitles],
            array_map(static fn (string $key) => $listed[$earlyAccessId][$key], ['audience_label', 'reply_to', 'event_title', 'early_access_tickets']),
        );
        $this->assertSame(
            [null, null, null, []],
            array_map(static fn (string $key) => $listed[$everyoneId][$key], ['audience_label', 'preset', 'event_title', 'early_access_tickets']),
        );
    }

    public static function reviewReasons(): array
    {
        return [
            [CampaignSendGate::REVIEW_REASON_FIRST_CAMPAIGN, 'This is the first campaign the account has sent.'],
            [CampaignSendGate::REVIEW_REASON_UNTRUSTED_ACCOUNT, 'The account is not trusted to send without review yet.'],
            [CampaignSendGate::REVIEW_REASON_RECENT_IMPORT, 'It reaches imported contacts that no approved campaign has reached yet.'],
        ];
    }

    #[DataProvider('reviewReasons')]
    public function test_the_review_notification_explains_the_reason_in_words(string $code, string $sentence): void
    {
        $html = (new CampaignPendingReviewMail(
            campaignId: 1,
            campaignName: 'Spring news',
            campaignSubject: 'Hello',
            accountName: 'Acme',
            accountId: 2,
            recipientsCount: 3,
            reason: $code,
        ))->render();

        $this->assertStringContainsString($sentence, $html);
        $this->assertStringNotContainsString($code, $html);
    }

    public function test_a_rejection_note_longer_than_the_limit_is_refused_and_changes_nothing(): void
    {
        Mail::fake();
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN']);

        $this->json('POST', "/admin/crm/campaigns/$pendingId/reject", ['note' => str_repeat('a', 501)], $this->superAdminHeaders())->assertStatus(422);

        $this->assertSame('PENDING_REVIEW', $this->campaign($pendingId)->status);
        Mail::assertNothingQueued();
    }

    public function test_cancelling_or_rejecting_a_campaign_waiting_for_review_drops_its_snapshot(): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        $otherId = $this->insertCampaign();

        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'PENDING_REVIEW');
        $this->crm('POST', "campaigns/$otherId/send", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'PENDING_REVIEW');
        $this->assertSame(2, DB::table('crm_campaign_recipients')->count());

        $this->crm('POST', "campaigns/{$this->campaignId}/cancel", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->json('POST', "/admin/crm/campaigns/$otherId/reject", [], $this->superAdminHeaders())->assertOk();

        $this->assertSame(0, DB::table('crm_campaign_recipients')->count());
        $this->crm('DELETE', "campaigns/{$this->campaignId}", [], $this->ownerHeaders())->assertNoContent();
        $this->crm('DELETE', "campaigns/$otherId", [], $this->ownerHeaders())->assertNoContent();
    }

    public function test_approving_queues_the_send_and_rejecting_marks_it_rejected_and_neither_can_be_repeated(): void
    {
        Bus::fake();
        $approvedId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN']);
        $rejectedId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'UNTRUSTED_ACCOUNT']);
        $headers = fn () => $this->superAdminHeaders();

        $this->json('POST', "/admin/crm/campaigns/$approvedId/approve", $this->reviewed($approvedId), $headers())->assertOk();
        $this->assertSame(['SENDING', null], [$this->campaign($approvedId)->status, $this->campaign($approvedId)->status_reason]);
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
        Bus::assertDispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->campaignId === $approvedId);

        $this->json('POST', "/admin/crm/campaigns/$rejectedId/reject", [], $headers())->assertOk();
        $this->assertSame(['REJECTED', 'REJECTED_BY_REVIEW'], [$this->campaign($rejectedId)->status, $this->campaign($rejectedId)->status_reason]);

        foreach ([$approvedId, $rejectedId, $this->campaignId] as $campaignId) {
            $this->json('POST', "/admin/crm/campaigns/$campaignId/approve", $this->reviewed($campaignId), $headers())->assertStatus(409);
            $this->json('POST', "/admin/crm/campaigns/$campaignId/reject", [], $headers())->assertStatus(409);
        }
        $this->json('POST', '/admin/crm/campaigns/999999999/approve', $this->reviewed(999999999), $headers())->assertNotFound();
        $this->json('POST', '/admin/crm/campaigns/999999999/reject', [], $headers())->assertNotFound();

        $this->assertSame('SENDING', $this->campaign($approvedId)->status);
        $this->assertSame('REJECTED', $this->campaign($rejectedId)->status);
        $this->assertSame('DRAFT', $this->campaign($this->campaignId)->status);
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
    }

    public function test_a_campaign_cancelled_while_waiting_for_review_can_no_longer_be_approved(): void
    {
        Bus::fake();
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW']);

        $this->crm('POST', "campaigns/$pendingId/cancel", [], $this->ownerHeaders())->assertOk();
        $this->json('POST', "/admin/crm/campaigns/$pendingId/approve", $this->reviewed($pendingId), $this->superAdminHeaders())->assertStatus(409);

        $this->assertSame('DRAFT', $this->campaign($pendingId)->status);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_an_approval_only_covers_the_version_the_reviewer_saw(): void
    {
        Bus::fake();
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN']);
        $reviewed = $this->reviewed($pendingId);
        DB::table('crm_campaigns')->where('id', $pendingId)->update(['body' => '<p>Swapped</p>', 'updated_at' => now()->addMinute()]);

        $this->json('POST', "/admin/crm/campaigns/$pendingId/approve", [], $this->superAdminHeaders())->assertStatus(422);
        $this->json('POST', "/admin/crm/campaigns/$pendingId/approve", $reviewed, $this->superAdminHeaders())->assertStatus(409);

        $this->assertSame('PENDING_REVIEW', $this->campaign($pendingId)->status);
        Bus::assertNotDispatched(DispatchCampaignJob::class);

        $this->json('POST', "/admin/crm/campaigns/$pendingId/approve", $this->reviewed($pendingId), $this->superAdminHeaders())->assertOk();
        $this->assertSame('SENDING', $this->campaign($pendingId)->status);
    }

    public function test_the_review_notification_shows_campaign_text_literally(): void
    {
        $html = (new CampaignPendingReviewMail(
            campaignId: 1,
            campaignName: "[Approve here](https://evil.example)\n\n# Urgent",
            campaignSubject: '**Bold** [link](https://evil.example)',
            accountName: "Acme\n- injected",
            accountId: 2,
            recipientsCount: 3,
            reason: 'FIRST_CAMPAIGN',
        ))->render();

        $this->assertStringNotContainsString('href="https://evil.example"', $html);
        $this->assertStringContainsString('[Approve here](https://evil.example) # Urgent', $html);
        $this->assertStringContainsString('**Bold** [link](https://evil.example)', $html);
        $this->assertStringContainsString('Acme - injected', $html);
    }

    public function test_members_without_view_financials_cannot_send_a_campaign_targeted_by_spend(): void
    {
        Bus::fake();
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spend = ['match' => 'all', 'rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 1, 'currency' => 'USD']]];
        $campaignId = $this->crm('POST', 'campaigns', ['name' => 'Big spenders', 'subject' => 'Hello', 'body' => '<p>Hi</p>', 'definition' => $spend], $this->ownerHeaders())
            ->assertCreated()
            ->json('data.id');

        $savedSegmentCampaignId = $this->insertCampaign([
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
            'segment_id' => $this->insertSegment($this->organizerId, 'Spenders', $spend['rules']),
        ]);

        foreach ([$campaignId, $savedSegmentCampaignId] as $id) {
            $this->crm('POST', "campaigns/$id/send", [], $this->memberHeaders($member))
                ->assertForbidden()
                ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
            $this->crm('POST', "campaigns/$id/send", ['scheduled_at' => '2099-01-15 12:00:00'], $this->memberHeaders($member))
                ->assertForbidden()
                ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');

            $this->assertSame('DRAFT', $this->campaign($id)->status);
            $this->assertSame(0, DB::table('crm_campaign_recipients')->where('campaign_id', $id)->count());
        }
        Bus::assertNothingDispatched();
    }

    public function test_a_member_without_view_financials_can_edit_a_spend_campaigns_content_without_dropping_its_hidden_rules(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spendRule = ['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD'];
        $countRule = ['field' => 'orders_count', 'operator' => 'gte', 'value' => 1];
        $campaignId = $this->insertCampaign(['segment_definition' => json_encode(['match' => 'all', 'rules' => [$spendRule, $countRule]])]);
        $seen = $this->crm('GET', "campaigns/$campaignId", [], $this->memberHeaders($member))->assertOk()->json('data');

        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'Renamed', 'subject' => 'New subject', 'definition' => $seen['definition']], $this->memberHeaders($member))
            ->assertOk()
            ->assertJsonPath('data.subject', 'New subject')
            ->assertJsonPath('data.has_restricted_rules', true);

        $stored = $this->campaign($campaignId);
        $this->assertSame('New subject', $stored->subject);
        $this->assertEquals([$spendRule, $countRule], json_decode($stored->segment_definition, true)['rules']);

        $before = $this->crmState();
        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'Renamed', 'definition' => ['match' => 'all', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 3]]]], $this->memberHeaders($member))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'Renamed', 'preset' => 'repeat_buyers'], $this->memberHeaders($member))
            ->assertForbidden();
        $this->assertSame($before, $this->crmState());
    }

    public function test_a_member_without_view_financials_can_edit_a_campaign_that_uses_a_saved_spend_segment_but_not_switch_its_audience(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $segmentId = $this->insertSegment($this->organizerId, 'Spenders', [['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD']]);
        $campaignId = $this->insertCampaign(['segment_id' => $segmentId]);

        $this->crm('GET', "campaigns/$campaignId", [], $this->memberHeaders($member))->assertOk()->assertJsonPath('data.has_restricted_rules', true);
        $this->crm('GET', "campaigns/$campaignId", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.has_restricted_rules', false);
        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'Renamed', 'subject' => 'Kept audience', 'segment_id' => $segmentId], $this->memberHeaders($member))
            ->assertOk()
            ->assertJsonPath('data.segment_id', $segmentId);
        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'Renamed', 'segment_id' => $this->segmentId], $this->memberHeaders($member))->assertForbidden();

        $this->assertSame([$segmentId, 'Kept audience'], [(int) $this->campaign($campaignId)->segment_id, $this->campaign($campaignId)->subject]);
    }

    public function test_a_member_without_view_financials_can_rename_a_spend_segment_but_not_overwrite_its_hidden_rules(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spendRule = ['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD'];
        $countRule = ['field' => 'orders_count', 'operator' => 'gte', 'value' => 1];
        $segmentId = $this->insertSegment($this->organizerId, 'Spenders', [$spendRule, $countRule]);
        $seen = collect($this->crm('GET', 'segments', [], $this->memberHeaders($member))->assertOk()->json('data'))->firstWhere('id', $segmentId);

        $this->crm('PUT', "segments/$segmentId", ['name' => 'Big spenders', 'definition' => $seen['definition']], $this->memberHeaders($member))
            ->assertOk()
            ->assertJsonPath('data.name', 'Big spenders')
            ->assertJsonPath('data.contacts_count', null)
            ->assertJsonPath('data.mailable_count', null);
        $stored = DB::table('crm_segments')->where('id', $segmentId)->first();
        $this->assertSame('Big spenders', $stored->name);
        $this->assertEquals([$spendRule, $countRule], json_decode($stored->definition, true)['rules']);

        $this->crm('PUT', "segments/$segmentId", ['name' => 'Big spenders', 'definition' => ['match' => 'all', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 5]]]], $this->memberHeaders($member))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
        $this->assertEquals([$spendRule, $countRule], json_decode(DB::table('crm_segments')->where('id', $segmentId)->value('definition'), true)['rules']);
    }

    public function test_an_approval_does_not_cover_a_campaign_withdrawn_edited_and_sent_for_review_again(): void
    {
        Bus::fake();
        config(['app.saas_mode_enabled' => true]);
        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'PENDING_REVIEW');
        $reviewed = $this->reviewed($this->campaignId);

        $this->crm('PUT', "campaigns/{$this->campaignId}", ['name' => 'Spring news', 'subject' => 'Hello', 'body' => '<p>Swapped while waiting</p>'], $this->ownerHeaders())->assertStatus(409);
        $this->assertSame($reviewed['reviewed_version'], $this->campaign($this->campaignId)->updated_at);

        $this->travel(1)->minutes();
        $this->crm('POST', "campaigns/{$this->campaignId}/cancel", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->crm('PUT', "campaigns/{$this->campaignId}", ['name' => 'Spring news', 'subject' => 'Hello', 'body' => '<p>Something else entirely</p>'], $this->ownerHeaders())->assertOk();
        $this->crm('POST', "campaigns/{$this->campaignId}/send", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'PENDING_REVIEW');

        $this->json('POST', "/admin/crm/campaigns/{$this->campaignId}/approve", $reviewed, $this->superAdminHeaders())->assertStatus(409);

        $this->assertSame('PENDING_REVIEW', $this->campaign($this->campaignId)->status);
        Bus::assertNotDispatched(DispatchCampaignJob::class);

        $this->json('POST', "/admin/crm/campaigns/{$this->campaignId}/approve", $this->reviewed($this->campaignId), $this->superAdminHeaders())->assertOk();
        $this->assertSame('SENDING', $this->campaign($this->campaignId)->status);
    }

    private function reviewed(int $campaignId): array
    {
        return ['reviewed_version' => DB::table('crm_campaigns')->where('id', $campaignId)->value('updated_at') ?? now()->toDateTimeString()];
    }

    private function campaignRepository(): CrmCampaignRepositoryInterface
    {
        return app(CrmCampaignRepositoryInterface::class);
    }

    private function useTier(array $limits): void
    {
        $tierId = DB::table('account_messaging_tiers')->insertGetId([
            'name' => 'Test tier '.uniqid(),
            'max_messages_per_24h' => 50,
            'max_recipients_per_message' => 5000,
            'links_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
            ...$limits,
        ]);

        DB::table('accounts')->where('id', $this->accountId)->update(['account_messaging_tier_id' => $tierId]);
    }

    private function superAdminHeaders(): array
    {
        return $this->memberHeaders($this->addAccountUser($this->accountId, Role::SUPERADMIN));
    }

    private function sentCampaignWithAnAttributedOrder(): int
    {
        $sentAt = now()->subDays(6);
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => $sentAt, 'recipients_count' => 1]);
        $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'message-ada', 'sent_at' => $sentAt]);
        $this->insertDelivery('message-ada', ['delivered_at' => $sentAt, 'first_clicked_at' => $sentAt->copy()->addHour()]);

        return $campaignId;
    }

    /**
     * @return Collection<int, SentMessage>
     */
    private function sentMessages(): Collection
    {
        return app('mail.manager')->mailer()->getSymfonyTransport()->messages();
    }
}
