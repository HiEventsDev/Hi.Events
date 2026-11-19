<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPendingReviewJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\SetAutomationEnabledHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\UpsertAutomationHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationDefaults;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationRunner;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationFailureDTO;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutomationHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    private const CONTENT = ['subject' => 'Hello', 'body' => '<p>Hello</p>'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => false]);
        $this->setUpCrmApi();
        DB::table('crm_automations')->delete();
    }

    public function test_every_trigger_has_a_template_with_defaults(): void
    {
        $defaults = app(AutomationDefaults::class);

        $templates = collect($this->crm('GET', 'automations/templates', [], $this->ownerHeaders())->assertOk()->json('data'))->keyBy('trigger');

        $this->assertSame(CrmAutomationTrigger::valuesArray(), $templates->keys()->all());
        foreach (CrmAutomationTrigger::cases() as $trigger) {
            $this->assertSame($defaults->name($trigger), $templates[$trigger->value]['name']);
            $this->assertSame($defaults->subject($trigger), $templates[$trigger->value]['subject']);
            $this->assertNotSame('', trim(strip_tags($templates[$trigger->value]['body'])));
            $this->assertSame($trigger->defaultPreset()?->value, $templates[$trigger->value]['preset']);
        }
        $this->assertSame(1, $templates['AFTER_EVENT']['delay_days']);
        $this->assertSame([60, 3, 'purchased'], [$templates['ABANDONED_CHECKOUT']['wait_minutes'], $templates['BEFORE_EVENT']['days_before'], $templates['BEFORE_EVENT']['occurrence_audience']]);
        $this->assertSame(0, DB::table('crm_automations')->count());
    }

    public function test_an_automation_is_created_switched_off_and_listed(): void
    {
        $created = $this->create('WELCOME', ['name' => '  Say hello ', 'subject' => '  Welcome aboard ', 'body' => '<p>Hello {{ organizer_name }}</p>'])
            ->assertCreated()
            ->json('data');

        $this->assertSame(
            ['Say hello', 'WELCOME', false, null, 'Welcome aboard', '<p>Hello {{ organizer_name }}</p>', null, 'first_timers'],
            [$created['name'], $created['trigger'], $created['is_enabled'], $created['enabled_at'], $created['subject'], $created['body'], $created['segment_id'], $created['preset']],
        );
        $listed = $this->crm('GET', 'automations', [], $this->ownerHeaders())->assertOk()->json('data');
        $this->assertSame([$created['id']], array_column($listed, 'id'));
    }

    public function test_an_organizer_can_have_several_automations_with_the_same_trigger(): void
    {
        $first = $this->create('BEFORE_EVENT', ['name' => 'A week before', 'days_before' => 7])->assertCreated()->json('data.id');
        $second = $this->create('BEFORE_EVENT', ['name' => 'The day before', 'days_before' => 1, 'occurrence_audience' => 'not_purchased'])->assertCreated()->json('data.id');

        $listed = collect($this->crm('GET', 'automations', [], $this->ownerHeaders())->json('data'))->keyBy('id');

        $this->assertSame([7, 'purchased'], [$listed[$first]['days_before'], $listed[$first]['occurrence_audience']]);
        $this->assertSame([1, 'not_purchased'], [$listed[$second]['days_before'], $listed[$second]['occurrence_audience']]);
    }

    public function test_only_the_settings_of_the_trigger_are_stored(): void
    {
        $this->create('ABANDONED_CHECKOUT', ['wait_minutes' => 180, 'days_before' => 5, 'delay_days' => 4])->assertCreated();
        $this->create('AFTER_EVENT', ['delay_days' => 3, 'wait_minutes' => 1440])->assertCreated();
        $this->create('WELCOME', ['delay_days' => 9])->assertCreated();

        $rows = DB::table('crm_automations')->orderBy('id')->get();
        $this->assertSame(['wait_minutes' => 180], json_decode($rows[0]->config, true));
        $this->assertSame([null, 3], [$rows[1]->config, (int) $rows[1]->delay_days]);
        $this->assertSame([null, 0], [$rows[2]->config, (int) $rows[2]->delay_days]);
    }

    public static function invalidSettings(): array
    {
        return [
            'wait that is not offered' => [['wait_minutes' => 45], 'wait_minutes'],
            'reminder on the day' => [['days_before' => 0], 'days_before'],
            'reminder too early' => [['days_before' => 15], 'days_before'],
            'unknown reminder audience' => [['occurrence_audience' => 'everyone'], 'occurrence_audience'],
            'delay too long' => [['delay_days' => 31], 'delay_days'],
            'unknown trigger' => [['trigger' => 'BIRTHDAY'], 'trigger'],
            'no name' => [['name' => ''], 'name'],
            'name too long' => [['name' => str_repeat('a', 81)], 'name'],
            'blank subject' => [['subject' => '   '], 'subject'],
            'body too long' => [['body' => str_repeat('a', 100001)], 'body'],
            'segment id that is not a number' => [['segment_id' => 'abc'], 'segment_id'],
        ];
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_automations_are_rejected_and_nothing_is_saved(array $body, string $field): void
    {
        $this->create('BEFORE_EVENT', $body)->assertUnprocessable()->assertJsonValidationErrors([$field]);

        $this->assertSame(0, DB::table('crm_automations')->count());
    }

    public function test_the_trigger_cannot_be_changed_after_creation(): void
    {
        $id = $this->create('WELCOME')->json('data.id');

        $this->update($id, ['trigger' => 'WIN_BACK'])->assertUnprocessable()->assertJsonValidationErrors(['trigger']);
        $this->update($id, ['name' => 'Renamed', 'subject' => 'Changed', 'preset' => 'repeat_buyers'])
            ->assertOk()
            ->assertJsonPath('data.trigger', 'WELCOME')
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.preset', 'repeat_buyers');
    }

    public static function defaultAudiences(): array
    {
        return [
            'win back defaults to lapsed' => ['WIN_BACK', [], null, 'lapsed'],
            'welcome defaults to first timers' => ['WELCOME', [], null, 'first_timers'],
            'segment automations default to subscribers' => ['SEGMENT_ENTERED', [], null, 'subscribers'],
            'announcements default to everyone subscribed' => ['EVENT_PUBLISHED', [], null, null],
            'abandoned checkouts default to everyone subscribed' => ['ABANDONED_CHECKOUT', [], null, null],
            'follow ups have no stored audience' => ['AFTER_EVENT', ['preset' => 'top_fans'], null, null],
            'a chosen preset is kept' => ['WIN_BACK', ['preset' => 'top_fans'], null, 'top_fans'],
            'a saved segment replaces the default preset' => ['SEGMENT_ENTERED', ['use_segment' => true], true, null],
        ];
    }

    #[DataProvider('defaultAudiences')]
    public function test_each_trigger_stores_its_default_audience(string $trigger, array $audience, ?bool $usesSegment, ?string $preset): void
    {
        if ($audience['use_segment'] ?? false) {
            $audience = ['segment_id' => $this->segmentId];
        }

        $this->create($trigger, $audience)
            ->assertCreated()
            ->assertJsonPath('data.preset', $preset)
            ->assertJsonPath('data.segment_id', $usesSegment ? $this->segmentId : null);
    }

    public function test_a_segment_of_another_organizer_is_not_found_and_saves_nothing(): void
    {
        $this->create('WIN_BACK', ['segment_id' => $this->otherSegmentId])->assertNotFound();
        $this->create('WIN_BACK', ['segment_id' => 999999999])->assertNotFound();
        $this->create('WIN_BACK', ['preset' => 'everyone'])->assertNotFound();
        $this->assertSame(0, DB::table('crm_automations')->count());

        $id = $this->create('WIN_BACK', ['segment_id' => $this->segmentId])->assertCreated()->json('data.id');
        $this->update($id, ['segment_id' => $this->otherSegmentId])->assertNotFound();
        $this->assertSame($this->segmentId, (int) DB::table('crm_automations')->value('segment_id'));
    }

    public function test_another_organizers_automation_cannot_be_read_changed_switched_or_deleted(): void
    {
        $theirs = $this->create('WELCOME', [], $this->otherOrganizerId)->assertCreated()->json('data.id');

        $this->update($theirs, ['name' => 'Mine now'])->assertNotFound();
        $this->crm('POST', "automations/$theirs/enable", [], $this->ownerHeaders())->assertNotFound();
        $this->crm('POST', "automations/$theirs/disable", [], $this->ownerHeaders())->assertNotFound();
        $this->crm('DELETE', "automations/$theirs", [], $this->ownerHeaders())->assertNoContent();

        $this->assertSame([], $this->crm('GET', 'automations', [], $this->ownerHeaders())->json('data'));
        $this->assertNull(DB::table('crm_automations')->where('id', $theirs)->value('deleted_at'));
    }

    public function test_the_reason_an_automation_could_not_send_is_listed_as_a_code_with_its_values(): void
    {
        $id = $this->create('WIN_BACK')->json('data.id');
        DB::table('crm_automations')->where('id', $id)->update([
            'last_failure_reason' => (new AutomationFailureDTO('TOO_MANY_RECIPIENTS', ['count' => 3, 'max' => 2]))->forStorage(),
        ]);

        $listed = $this->crm('GET', 'automations', [], $this->ownerHeaders())->assertOk()->json('data.0');

        $this->assertSame(['TOO_MANY_RECIPIENTS', ['count' => 3, 'max' => 2]], [$listed['last_failure_reason'], $listed['last_failure_reason_params']]);
    }

    public function test_switching_on_stamps_the_start_and_switching_off_clears_it(): void
    {
        $this->freezeSecond();
        $id = $this->create('WIN_BACK')->json('data.id');
        DB::table('crm_automations')->where('id', $id)->update(['last_failure_reason' => (new AutomationFailureDTO('DAILY_LIMIT_REACHED'))->forStorage()]);

        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.last_failure_reason', null)
            ->assertJsonPath('data.last_failure_reason_params', null);
        $enabledAt = now()->toDateTimeString();
        $this->assertSame($enabledAt, DB::table('crm_automations')->value('enabled_at'));

        $this->travel(2)->hours();
        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();
        $this->update($id, ['subject' => 'Edited'])->assertOk()->assertJsonPath('data.is_enabled', true);
        $this->assertSame($enabledAt, DB::table('crm_automations')->value('enabled_at'));

        $this->crm('POST', "automations/$id/disable", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false)
            ->assertJsonPath('data.enabled_at', null);
    }

    public function test_switching_on_announcements_remembers_the_events_already_live_so_only_later_ones_are_announced(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->freezeSecond();
        config(['app.event_spam_check_enabled' => false]);
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);
        $settled = ['status' => 'LIVE', 'created_at' => now()->subDays(30), 'updated_at' => now()->subHours(2)];
        DB::table('events')->where('id', $this->eventId)->update($settled);
        $draftId = $this->createEvent($this->accountId, $this->organizerId, 'Old Draft');
        DB::table('events')->where('id', $draftId)->update([...$settled, 'status' => 'DRAFT']);
        $this->insertOccurrence($draftId, now()->addDays(15));
        DB::table('events')->where('id', $this->otherEventId)->update($settled);
        $this->insertOccurrence($this->otherEventId, now()->addDays(15));
        $id = $this->create('EVENT_PUBLISHED', ['subject' => 'Just announced: {{ event_title }}'])->json('data.id');

        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();
        $this->assertSame([$this->eventId], $this->baselineEventIds($id));

        DB::table('events')->where('id', $draftId)->update(['status' => 'LIVE']);
        app(AutomationRunner::class)->run(app(CrmAutomationRepositoryInterface::class)->findById($id));

        $announced = DB::table('crm_campaigns')->where('automation_id', $id)->sole();
        $this->assertSame(["event:$draftId", 'Just announced: Old Draft'], [$announced->automation_run_key, $announced->subject]);

        $this->crm('POST', "automations/$id/disable", [], $this->ownerHeaders())->assertOk();
        $this->assertNull($this->baselineEventIds($id));
        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();
        $this->assertEqualsCanonicalizing([$this->eventId, $draftId], $this->baselineEventIds($id));
    }

    public function test_in_saas_mode_what_an_edited_switched_on_automation_sends_next_waits_for_review_with_the_edited_content(): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        config(['app.saas_mode_enabled' => true, 'app.event_spam_check_enabled' => false]);
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Untrusted')]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'DRAFT', 'created_at' => now()->subDays(30), 'updated_at' => now()->subHours(2)]);
        $id = $this->create('EVENT_PUBLISHED', ['subject' => 'Just announced: {{ event_title }}'])->json('data.id');
        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();

        $this->update($id, ['subject' => 'Edited: {{ event_title }}', 'body' => '<p>Edited body</p>'])
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        app(AutomationRunner::class)->run(app(CrmAutomationRepositoryInterface::class)->findById($id));

        $campaign = DB::table('crm_campaigns')->where('automation_id', $id)->sole();
        $this->assertSame(['PENDING_REVIEW', 'Edited: Main Event', '<p>Edited body</p>'], [$campaign->status, $campaign->subject, $campaign->body]);
        Bus::assertDispatched(NotifyCampaignPendingReviewJob::class);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_switching_on_a_segment_automation_records_current_members_without_emailing_them(): void
    {
        $id = $this->create('SEGMENT_ENTERED', ['segment_id' => $this->segmentId])->json('data.id');

        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();

        $this->assertSame([$this->contactId], DB::table('crm_automation_memberships')->where('automation_id', $id)->pluck('contact_id')->map(static fn ($contactId) => (int) $contactId)->all());
        $this->assertNotNull(DB::table('crm_automations')->where('id', $id)->value('segment_definition_hash'));
        $this->assertSame(0, DB::table('crm_campaigns')->where('automation_id', $id)->count());

        $this->travel(15)->minutes();
        app(AutomationRunner::class)->run(app(CrmAutomationRepositoryInterface::class)->findById($id));
        $this->assertSame([$this->contactId], DB::table('crm_automation_memberships')->where('automation_id', $id)->pluck('contact_id')->map(static fn ($contactId) => (int) $contactId)->all());
        $this->assertSame(0, DB::table('crm_campaigns')->where('automation_id', $id)->count());

        $this->crm('POST', "automations/$id/disable", [], $this->ownerHeaders())->assertOk();
        $this->assertSame(0, DB::table('crm_automation_memberships')->where('automation_id', $id)->count());
        $this->assertNull(DB::table('crm_automations')->where('id', $id)->value('segment_definition_hash'));
    }

    public function test_switching_a_segment_automation_on_and_then_running_it_does_not_baseline_again(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $id = $this->create('SEGMENT_ENTERED', ['segment_id' => $this->segmentId])->json('data.id');
        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();
        $hash = DB::table('crm_automations')->where('id', $id)->value('segment_definition_hash');

        $this->insertSubscribedContact('newcomer@example.com', ['orders_count' => 1]);
        $this->travel(15)->minutes();
        app(AutomationRunner::class)->run(app(CrmAutomationRepositoryInterface::class)->findById($id));

        $this->assertSame($hash, DB::table('crm_automations')->where('id', $id)->value('segment_definition_hash'));
        $campaignId = DB::table('crm_campaigns')->where('automation_id', $id)->sole()->id;
        $this->assertSame(['newcomer@example.com'], DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->pluck('email')->all());
    }

    public function test_only_twenty_segment_automations_can_be_switched_on_at_once(): void
    {
        foreach (range(1, SetAutomationEnabledHandler::MAX_ENABLED_SEGMENT_AUTOMATIONS) as $i) {
            $this->crm('POST', 'automations/'.$this->create('SEGMENT_ENTERED', ['name' => "Segment $i"])->json('data.id').'/enable', [], $this->ownerHeaders())->assertOk();
        }

        $extra = $this->create('SEGMENT_ENTERED', ['name' => 'One too many'])->json('data.id');

        $this->crm('POST', "automations/$extra/enable", [], $this->ownerHeaders())->assertStatus(409);
        $this->assertFalse((bool) DB::table('crm_automations')->where('id', $extra)->value('is_enabled'));
    }

    public function test_an_organizer_can_have_at_most_fifty_automations(): void
    {
        DB::table('crm_automations')->insert(array_map(fn (int $i) => [
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => "Automation $i",
            'trigger' => 'WELCOME',
            'subject' => 'Hello',
            'body' => '<p>Hello</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ], range(1, UpsertAutomationHandler::MAX_AUTOMATIONS_PER_ORGANIZER)));

        $this->create('WELCOME')->assertStatus(409);
    }

    public function test_deleting_an_automation_switches_it_off_and_keeps_its_campaigns(): void
    {
        $id = $this->create('SEGMENT_ENTERED')->json('data.id');
        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'automation_id' => $id, 'automation_run_key' => 'entered:202601010000']);

        $this->crm('DELETE', "automations/$id", [], $this->ownerHeaders())->assertNoContent();

        $row = DB::table('crm_automations')->where('id', $id)->first();
        $this->assertSame([false, true], [(bool) $row->is_enabled, $row->deleted_at !== null]);
        $this->assertSame(0, DB::table('crm_automation_memberships')->where('automation_id', $id)->count());
        $this->assertSame(1, DB::table('crm_campaigns')->where('id', $campaignId)->count());
        $this->assertSame([], $this->crm('GET', 'automations', [], $this->ownerHeaders())->json('data'));
        $this->assertFalse(app(CrmAutomationRepositoryInterface::class)->findEnabledAccountIdsByIdAfter(0, PHP_INT_MAX)->has($id));
    }

    public static function waysToStopAnAutomation(): array
    {
        return [
            'switching it off' => ['POST', 'disable'],
            'deleting it' => ['DELETE', null],
        ];
    }

    #[DataProvider('waysToStopAnAutomation')]
    public function test_stopping_an_automation_cancels_its_emails_waiting_for_review_and_stops_the_one_going_out(string $method, ?string $action): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $id = $this->create('WIN_BACK')->json('data.id');
        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();
        $adaId = $this->contactId;
        $inReviewId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'launched_at' => now(), 'automation_id' => $id, 'automation_run_key' => 'date:2026-01-01']);
        $waitingId = $this->insertRecipient($inReviewId, $adaId, 'ada@example.com');
        $sendingId = $this->insertCampaign(['status' => 'SENDING', 'launched_at' => now(), 'sent_at' => now(), 'automation_id' => $id, 'automation_run_key' => 'date:2026-01-02']);
        $sentId = $this->insertRecipient($sendingId, $adaId, 'ada@example.com', ['status' => 'SENT']);
        $queuedId = $this->insertRecipient($sendingId, $this->insertSubscribedContact('grace@example.com'), 'grace@example.com');
        $manualId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN']);
        $reviewedVersion = DB::table('crm_campaigns')->where('id', $inReviewId)->value('updated_at');

        $this->crm($method, 'automations/'.$id.($action === null ? '' : '/'.$action), [], $this->ownerHeaders())->assertSuccessful();

        $this->assertSame(
            [['CANCELLED', 'AUTOMATION_STOPPED'], ['CANCELLED', 'AUTOMATION_STOPPED'], ['PENDING_REVIEW', 'FIRST_CAMPAIGN']],
            array_map(fn (int $campaignId) => [$this->campaign($campaignId)->status, $this->campaign($campaignId)->status_reason], [$inReviewId, $sendingId, $manualId]),
        );
        $this->assertSame(
            ['SKIPPED', 'SENT', 'SKIPPED'],
            array_map(static fn (int $recipientId) => DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('status'), [$waitingId, $sentId, $queuedId]),
        );
        $this->json('POST', "/admin/crm/campaigns/$inReviewId/approve", ['reviewed_version' => $reviewedVersion], $this->memberHeaders($this->addAccountUser($this->accountId, Role::SUPERADMIN)))
            ->assertStatus(409);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_an_email_created_for_review_just_as_its_automation_was_switched_off_is_withdrawn_instead_of_approved(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $id = $this->create('WIN_BACK')->json('data.id');
        $inReviewId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'launched_at' => now(), 'automation_id' => $id, 'automation_run_key' => 'date:2026-01-01']);
        $this->insertRecipient($inReviewId, $this->contactId, 'ada@example.com');

        $this->json(
            'POST',
            "/admin/crm/campaigns/$inReviewId/approve",
            ['reviewed_version' => DB::table('crm_campaigns')->where('id', $inReviewId)->value('updated_at')],
            $this->memberHeaders($this->addAccountUser($this->accountId, Role::SUPERADMIN)),
        )->assertStatus(409);

        $campaign = $this->campaign($inReviewId);
        $this->assertSame(['CANCELLED', 'AUTOMATION_STOPPED'], [$campaign->status, $campaign->status_reason]);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_switching_on_announcements_never_announces_an_event_that_was_already_published_but_is_off_sale_for_now(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        config(['app.event_spam_check_enabled' => false]);
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);
        $this->insertSubscribedContact('fan@example.com');
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'PENDING_MANUAL_REVIEW', 'created_at' => now()->subDays(30), 'updated_at' => now()->subHours(2)]);
        $id = $this->create('EVENT_PUBLISHED')->json('data.id');

        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        app(AutomationRunner::class)->run(app(CrmAutomationRepositoryInterface::class)->findById($id));

        $this->assertSame(0, DB::table('crm_campaigns')->where('automation_id', $id)->count());
    }

    public function test_an_enabled_automation_keeps_its_segment_from_being_deleted_until_it_is_removed(): void
    {
        $id = $this->create('WIN_BACK', ['name' => 'Bring them back', 'segment_id' => $this->segmentId])->json('data.id');
        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();

        $this->crm('DELETE', "segments/{$this->segmentId}", [], $this->ownerHeaders())
            ->assertStatus(409)
            ->assertJsonPath('message', 'This segment is still in use: Automation "Bring them back". Cancel the campaign or change who the automation sends to first.');

        $this->crm('DELETE', "automations/$id", [], $this->ownerHeaders())->assertNoContent();
        $this->crm('DELETE', "segments/{$this->segmentId}", [], $this->ownerHeaders())->assertNoContent();
    }

    public function test_the_body_is_purified_before_it_is_stored(): void
    {
        $stored = $this->create('WELCOME', [
            'body' => '<p>Hello {{ organizer_name }}</p><script>alert(1)</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)">x</a>',
        ])->assertCreated()->json('data.body');

        $this->assertStringContainsString('<p>Hello {{ organizer_name }}</p>', $stored);
        $this->assertStringNotContainsStringIgnoringCase('script', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
        $this->assertStringNotContainsString('alert(', $stored);
    }

    public function test_members_without_view_financials_cannot_point_an_automation_at_a_spend_segment(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spendSegmentId = $this->insertSegment($this->organizerId, 'Big spenders', [['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD']]);
        $body = ['name' => 'Spenders', 'trigger' => 'WIN_BACK', ...self::CONTENT, 'segment_id' => $spendSegmentId];

        $this->crm('POST', 'automations', $body, $this->memberHeaders($member))->assertUnprocessable();
        $this->assertSame(0, DB::table('crm_automations')->count());

        $this->crm('POST', 'automations', $body, $this->ownerHeaders())->assertCreated()->assertJsonPath('data.segment_id', $spendSegmentId);
    }

    public function test_members_without_view_financials_cannot_switch_on_an_automation_that_sends_to_a_spend_segment(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spendSegmentId = $this->insertSegment($this->organizerId, 'Big spenders', [['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD']]);
        $id = $this->create('WIN_BACK', ['segment_id' => $spendSegmentId])->assertCreated()->json('data.id');

        $this->crm('POST', "automations/$id/enable", [], $this->memberHeaders($member))->assertUnprocessable();
        $this->assertFalse((bool) DB::table('crm_automations')->where('id', $id)->value('is_enabled'));

        $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.is_enabled', true);
    }

    public function test_the_list_reports_how_many_emails_each_automation_has_sent(): void
    {
        $welcomeId = $this->create('WELCOME')->json('data.id');
        $winBackId = $this->create('WIN_BACK')->json('data.id');
        $lastSentAt = now()->subHours(2)->startOfSecond();
        $firstRunId = $this->insertCampaign(['status' => 'SENT', 'automation_id' => $welcomeId, 'automation_run_key' => 'date:2026-01-01']);
        $secondRunId = $this->insertCampaign(['status' => 'SENDING', 'automation_id' => $welcomeId, 'automation_run_key' => 'date:2026-01-02']);
        $this->insertRecipient($firstRunId, $this->contactId, 'ada@example.com', ['status' => 'SENT', 'sent_at' => $lastSentAt->copy()->subDay()]);
        $this->insertRecipient($firstRunId, $this->secondContactId, 'second@example.com', ['status' => 'FAILED']);
        $this->insertRecipient($secondRunId, $this->contactId, 'ada@example.com', ['status' => 'SENT', 'sent_at' => $lastSentAt]);

        $listed = collect($this->crm('GET', 'automations', [], $this->ownerHeaders())->assertOk()->json('data'))->keyBy('id');

        $this->assertSame([2, $lastSentAt->toDateTimeString()], [$listed[$welcomeId]['sent_count'], $listed[$welcomeId]['last_sent_at']]);
        $this->assertSame([0, null], [$listed[$winBackId]['sent_count'], $listed[$winBackId]['last_sent_at']]);
    }

    public function test_creating_and_switching_on_an_automation_sends_nothing(): void
    {
        Bus::fake();
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);
        $campaignsBefore = DB::table('crm_campaigns')->count();

        foreach (CrmAutomationTrigger::cases() as $trigger) {
            $id = $this->create($trigger->value)->assertCreated()->json('data.id');
            $this->crm('POST', "automations/$id/enable", [], $this->ownerHeaders())->assertOk();
        }

        $this->assertSame($campaignsBefore, DB::table('crm_campaigns')->count());
        $this->assertSame(0, DB::table('crm_campaign_recipients')->count());
        Bus::assertNothingDispatched();
    }

    public function test_the_announcement_preview_counts_who_would_get_it_and_renders_a_real_upcoming_event_without_writing_anything(): void
    {
        Bus::fake();
        $this->freezeSecond();
        DB::table('users')->where('id', $this->owner->id)->update(['first_name' => 'Grace']);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE', 'title' => 'Jazz & Blues Night']);
        $before = $this->crmState();

        $preview = $this->preview('EVENT_PUBLISHED', [
            'subject' => 'Just announced: {{ event_title }}',
            'body' => '<p>Hi {{ first_name | default: &quot;there&quot; }}, {{ event_title }} is on {{ event_date }}. From {{ organizer_name }}</p>',
        ])->assertOk()->json('data');

        $this->assertSame(1, $preview['mailable_count']);
        $this->assertSame(['kind' => 'NEXT_PUBLISH', 'at' => null, 'event_title' => null, 'event_date' => null], $preview['first_send']);
        $this->assertSame('Just announced: Jazz & Blues Night', $preview['subject']);
        $this->assertStringContainsString('Hi Grace, Jazz &amp; Blues Night is on '.now()->addDays(10)->isoFormat('LL'), $preview['html']);
        $this->assertSame($before, $this->crmState());
        Bus::assertNothingDispatched();
    }

    public function test_the_preview_uses_a_sample_event_when_the_organizer_has_none_coming_up(): void
    {
        $this->preview('EVENT_PUBLISHED', ['subject' => 'Just announced: {{ event_title }}'])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Just announced: Your next event');
    }

    public function test_a_daily_or_segment_automation_preview_says_it_first_runs_at_the_next_quarter_hour(): void
    {
        $this->travelTo(Carbon::parse('2027-03-01 10:07:30', 'UTC'));

        $this->preview('WIN_BACK', ['preset' => 'repeat_buyers'])
            ->assertOk()
            ->assertJsonPath('data.first_send.kind', 'DAILY')
            ->assertJsonPath('data.first_send.at', '2027-03-01T10:15:00.000000Z');
        $this->preview('SEGMENT_ENTERED')
            ->assertOk()
            ->assertJsonPath('data.first_send.kind', 'ON_ENTRY')
            ->assertJsonPath('data.first_send.at', '2027-03-01T10:15:00.000000Z');
    }

    public function test_a_daily_automation_preview_before_ten_in_the_organizers_morning_says_it_first_runs_at_ten(): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update(['timezone' => 'Europe/Paris']);
        $this->travelTo(Carbon::parse('2027-03-01 08:07:30', 'Europe/Paris'));

        $this->preview('WELCOME')
            ->assertOk()
            ->assertJsonPath('data.first_send.at', Carbon::parse('2027-03-01 10:00:00', 'Europe/Paris')->utc()->toISOString());
    }

    public function test_triggers_that_only_reach_people_who_arrive_after_switching_on_preview_nobody_now(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);

        foreach (['WELCOME', 'SEGMENT_ENTERED', 'ABANDONED_CHECKOUT'] as $trigger) {
            $this->preview($trigger)->assertOk()->assertJsonPath('data.mailable_count', 0);
        }
        $this->preview('WIN_BACK', ['preset' => 'subscribers'])->assertOk()->assertJsonPath('data.mailable_count', 1);
    }

    public function test_the_preview_of_a_saved_win_back_leaves_out_people_it_emailed_inside_its_repeat_window(): void
    {
        $id = $this->create('WIN_BACK', ['preset' => 'subscribers'])->json('data.id');
        $earlierRunId = $this->insertCampaign(['status' => 'SENT', 'automation_id' => $id, 'automation_run_key' => 'date:earlier']);
        $this->insertRecipient($earlierRunId, $this->contactId, 'ada@example.com', ['status' => 'SENT', 'sent_at' => now()->subDays(10), 'created_at' => now()->subDays(10)]);

        $this->preview('WIN_BACK', ['preset' => 'subscribers'])->assertOk()->assertJsonPath('data.mailable_count', 1);
        $this->preview('WIN_BACK', ['preset' => 'subscribers', 'automation_id' => $id])->assertOk()->assertJsonPath('data.mailable_count', 0);

        DB::table('crm_campaign_recipients')->where('campaign_id', $earlierRunId)->update(['created_at' => now()->subDays(181)]);
        $this->preview('WIN_BACK', ['preset' => 'subscribers', 'automation_id' => $id])->assertOk()->assertJsonPath('data.mailable_count', 1);

        $this->preview('WIN_BACK', ['automation_id' => 999999999])->assertNotFound();
    }

    public function test_the_preview_of_a_daily_automation_that_already_ran_today_says_it_next_runs_tomorrow_at_ten(): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update(['timezone' => 'Europe/Paris']);
        $this->travelTo(Carbon::parse('2027-03-01 14:07:30', 'Europe/Paris'));
        $id = $this->create('WIN_BACK')->json('data.id');
        DB::table('crm_automations')->where('id', $id)->update(['last_run_at' => Carbon::parse('2027-03-01 10:00:00', 'Europe/Paris')->utc()]);

        $this->preview('WIN_BACK', ['automation_id' => $id])
            ->assertOk()
            ->assertJsonPath('data.first_send.at', Carbon::parse('2027-03-02 10:00:00', 'Europe/Paris')->utc()->toISOString());

        DB::table('crm_automations')->where('id', $id)->update(['last_run_at' => Carbon::parse('2027-02-28 10:00:00', 'Europe/Paris')->utc()]);
        $this->preview('WIN_BACK', ['automation_id' => $id])
            ->assertOk()
            ->assertJsonPath('data.first_send.at', '2027-03-01T13:15:00.000000Z');
    }

    public function test_an_after_event_preview_names_the_next_date_to_end_and_counts_its_ticket_holders(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $occurrence = DB::table('event_occurrences')->where('id', $this->occurrenceId)->first();

        $this->preview('AFTER_EVENT', ['subject' => 'Thanks for coming to {{ event_title }}', 'delay_days' => 2])
            ->assertOk()
            ->assertJsonPath('data.mailable_count', 1)
            ->assertJsonPath('data.first_send.kind', 'AFTER_DATE')
            ->assertJsonPath('data.first_send.event_title', 'Main Event')
            ->assertJsonPath('data.first_send.at', Carbon::parse($occurrence->end_date, 'UTC')->addDays(2)->toISOString())
            ->assertJsonPath('data.subject', 'Thanks for coming to Main Event');
    }

    public function test_a_reminder_preview_names_the_next_date_and_when_its_email_goes_out(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE', 'timezone' => 'Europe/Dublin']);
        $occurrence = DB::table('event_occurrences')->where('id', $this->occurrenceId)->first();
        $expected = Carbon::parse($occurrence->start_date, 'UTC')->setTimezone('Europe/Dublin')->subDays(3)->setTime(10, 0)->utc();

        $this->preview('BEFORE_EVENT', ['days_before' => 3, 'occurrence_audience' => 'purchased'])
            ->assertOk()
            ->assertJsonPath('data.first_send.kind', 'BEFORE_DATE')
            ->assertJsonPath('data.first_send.at', $expected->toISOString())
            ->assertJsonPath('data.mailable_count', 1);
        $this->preview('BEFORE_EVENT', ['days_before' => 3, 'occurrence_audience' => 'not_purchased'])
            ->assertOk()
            ->assertJsonPath('data.mailable_count', 0);
    }

    public function test_the_preview_refuses_a_segment_of_another_organizer_or_a_spend_segment_for_members_without_view_financials(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spendSegmentId = $this->insertSegment($this->organizerId, 'Spenders', [['field' => 'total_spent', 'operator' => 'gte', 'value' => 1, 'currency' => 'USD']]);
        $body = ['trigger' => 'WIN_BACK', ...self::CONTENT];

        $this->crm('POST', 'automations/preview', [...$body, 'segment_id' => $this->otherSegmentId], $this->ownerHeaders())->assertNotFound();
        $this->crm('POST', 'automations/test', [...$body, 'segment_id' => $this->otherSegmentId], $this->ownerHeaders())->assertNotFound();
        $this->crm('POST', 'automations/preview', [...$body, 'segment_id' => $spendSegmentId], $this->memberHeaders($member))->assertUnprocessable();
        $this->crm('POST', 'automations/preview', [...$body, 'segment_id' => $spendSegmentId], $this->ownerHeaders())->assertOk();
        $this->crm('POST', 'automations/preview', [...$body, 'trigger' => 'UNKNOWN'], $this->ownerHeaders())->assertUnprocessable();
    }

    public function test_an_automation_test_goes_only_to_the_signed_in_user(): void
    {
        Bus::fake();
        $this->verifyAccount();
        $member = $this->addAccountUser($this->accountId);
        DB::table('users')->where('id', $member->id)->update(['first_name' => 'Grace']);
        $before = $this->crmState();

        $this->crm('POST', 'automations/test', ['trigger' => 'WELCOME', 'subject' => 'Welcome {{ first_name }}', 'body' => '<p>Thanks from {{ organizer_name }}</p>'], $this->memberHeaders($member))
            ->assertOk()
            ->assertJsonPath('message', "Test email sent to {$member->email}");

        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame([$member->email], array_map(static fn ($address) => $address->getAddress(), $email->getTo()));
        $this->assertSame('[Test] Welcome Grace', $email->getSubject());
        $this->assertSame($before, $this->crmState());
    }

    public function test_an_automation_test_needs_an_account_that_may_send_and_an_editor_with_a_licence(): void
    {
        $body = ['trigger' => 'WELCOME', ...self::CONTENT];
        DB::table('accounts')->where('id', $this->accountId)->update(['account_verified_at' => null]);

        $this->crm('POST', 'automations/test', $body, $this->ownerHeaders())->assertUnprocessable();

        $this->verifyAccount();
        $this->crm('POST', 'automations/test', [...$body, 'body' => '<p> </p>'], $this->ownerHeaders())->assertUnprocessable();
        $this->crm('POST', 'automations/test', $body, $this->memberHeaders($this->addAccountUser($this->accountId, Role::VIEWER)))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'READ_ONLY_ROLE');

        $this->useLicenceKey(null);
        $this->crm('POST', 'automations/test', $body, $this->ownerHeaders())
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');
        $this->assertCount(0, app('mail.manager')->mailer()->getSymfonyTransport()->messages());
    }

    /**
     * @return int[]|null
     */
    private function baselineEventIds(int $automationId): ?array
    {
        $stored = DB::table('crm_automations')->where('id', $automationId)->value('baseline_event_ids');

        return $stored === null ? null : json_decode($stored, true);
    }

    private function create(string $trigger, array $body = [], ?int $organizerId = null): TestResponse
    {
        return $this->crm('POST', 'automations', ['name' => 'My automation', 'trigger' => $trigger, ...self::CONTENT, ...$body], $this->ownerHeaders(), $organizerId);
    }

    private function update(int $automationId, array $body): TestResponse
    {
        return $this->crm('PUT', "automations/$automationId", ['name' => 'My automation', ...self::CONTENT, ...$body], $this->ownerHeaders());
    }

    private function preview(string $trigger, array $body = []): TestResponse
    {
        return $this->crm('POST', 'automations/preview', ['trigger' => $trigger, ...self::CONTENT, ...$body], $this->ownerHeaders());
    }
}
