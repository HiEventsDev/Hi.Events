<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\CampaignReviewExpiredException;
use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\SendCampaignEmailJob;
use HiEvents\Enterprise\Crm\Jobs\SendScheduledCampaignsJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationRunner;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\BeforeEventTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\SegmentEnteredTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDeliveryService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDispatchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSendGate;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\CampaignSupportInbox;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncService;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Domain\Message\MessagingEligibilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutomationTriggersTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2027-05-10 12:00:00', 'UTC'));
        config(['app.saas_mode_enabled' => false, 'app.event_spam_check_enabled' => false]);
        $this->setUpCrmOrganizer();
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        Bus::fake([DispatchCampaignJob::class]);
    }

    public function test_a_subscriber_who_abandoned_a_checkout_gets_one_recovery_email_for_that_event(): void
    {
        $automationId = $this->automation('ABANDONED_CHECKOUT', ['config' => json_encode(['wait_minutes' => 60])]);
        $contactId = $this->insertSubscribedContact('ada@example.com');
        $this->insertSubscribedContact('grace@example.com');
        $this->abandonedOrder('ada@example.com', minutesAgo: 90);

        $this->runAutomation($automationId);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertStringStartsWith("abandon:{$this->eventId}:", $campaign->automation_run_key);
        $this->assertSame([$this->eventId, true, true, 'SENDING'], [(int) $campaign->event_id, (bool) $campaign->audience_is_explicit, (bool) $campaign->skips_buyers, $campaign->status]);
        $this->assertSame([$contactId], DB::table('crm_campaign_recipients')->where('campaign_id', $campaign->id)->pluck('contact_id')->map(static fn ($id) => (int) $id)->all());
    }

    #[DataProvider('triggersBatchedWhileInReview')]
    public function test_while_one_run_waits_for_review_later_runs_are_held_and_then_sent_as_one(string $trigger): void
    {
        config(['app.saas_mode_enabled' => true]);
        $automationId = $this->automation($trigger, $trigger === 'SEGMENT_ENTERED' ? ['preset_segment' => 'subscribers'] : ['config' => json_encode(['wait_minutes' => 60])]);
        $this->runAutomation($automationId);
        $arrive = function (string $email): void {
            $this->insertSubscribedContact($email);
            $this->abandonedOrder($email, minutesAgo: 90);
        };

        $arrive('ada@example.com');
        $this->runAutomation($automationId);
        $inReview = $this->campaigns($automationId)->sole();
        $this->assertSame('PENDING_REVIEW', $inReview->status);

        foreach (['grace@example.com', 'alan@example.com'] as $email) {
            $this->travel(15)->minutes();
            $arrive($email);
            $this->runAutomation($automationId);
        }

        $this->assertCount(1, $this->campaigns($automationId));

        app(CampaignLaunchService::class)->approve(app(CrmCampaignRepositoryInterface::class)->findById((int) $inReview->id), Carbon::parse($inReview->updated_at));
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $campaigns = $this->campaigns($automationId);
        $this->assertCount(2, $campaigns);
        $this->assertSame(['ada@example.com'], $this->recipientEmails((int) $campaigns[0]->id));
        $this->assertSame(['alan@example.com', 'grace@example.com'], $this->recipientEmails((int) $campaigns[1]->id));
    }

    public static function triggersBatchedWhileInReview(): array
    {
        return ['abandoned checkout' => ['ABANDONED_CHECKOUT'], 'segment entry' => ['SEGMENT_ENTERED']];
    }

    public function test_a_run_that_expired_in_review_stays_reported_until_a_later_run_is_actually_sent(): void
    {
        config(['app.saas_mode_enabled' => true]);
        $automationId = $this->automation('ABANDONED_CHECKOUT', ['config' => json_encode(['wait_minutes' => 60])]);
        $this->insertSubscribedContact('ada@example.com');
        $this->abandonedOrder('ada@example.com', minutesAgo: 90);
        $this->runAutomation($automationId);
        $reason = fn (): ?string => json_decode((string) DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'), true)['code'] ?? null;

        $this->travel(CampaignLaunchService::AUTOMATION_REVIEW_WINDOW_HOURS + 1)->hours();
        SendScheduledCampaignsJob::dispatchSync();
        $this->assertSame(['CANCELLED', 'REVIEW_EXPIRED'], [$this->campaigns($automationId)->sole()->status, $reason()]);

        $this->insertSubscribedContact('grace@example.com');
        $this->abandonedOrder('grace@example.com', minutesAgo: 90);
        $this->runAutomation($automationId);
        $second = $this->campaigns($automationId)->last();
        $this->assertSame(['PENDING_REVIEW', 'REVIEW_EXPIRED'], [$second->status, $reason()]);

        app(CampaignLaunchService::class)->approve(app(CrmCampaignRepositoryInterface::class)->findById((int) $second->id), Carbon::parse($second->updated_at));
        DB::table('crm_campaigns')->where('id', $second->id)->update(['status' => 'SENT', 'sent_at' => now()]);
        $this->travel(15)->minutes();
        $this->insertSubscribedContact('alan@example.com');
        $this->abandonedOrder('alan@example.com', minutesAgo: 90);
        $this->runAutomation($automationId);

        $this->assertSame(['SENDING', null], [$this->campaigns($automationId)->last()->status, $reason()]);
    }

    public function test_a_rejected_recovery_email_is_not_recreated_for_the_same_people_in_later_runs(): void
    {
        config(['app.saas_mode_enabled' => true]);
        $automationId = $this->automation('ABANDONED_CHECKOUT', ['config' => json_encode(['wait_minutes' => 60])]);
        $contactId = $this->insertSubscribedContact('ada@example.com');
        $this->abandonedOrder('ada@example.com', minutesAgo: 90);
        $this->runAutomation($automationId);
        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame('PENDING_REVIEW', $campaign->status);

        $this->assertTrue(app(CampaignLaunchService::class)->reject(app(CrmCampaignRepositoryInterface::class)->findById((int) $campaign->id), null));
        $this->assertSame([[$contactId, 'SKIPPED']], DB::table('crm_campaign_recipients')->where('campaign_id', $campaign->id)->get()->map(static fn ($row) => [(int) $row->contact_id, $row->status])->all());

        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $this->assertSame('CANCELLED', $this->campaigns($automationId)->sole()->status);
    }

    public function test_a_draft_left_behind_by_a_crashed_run_is_replaced_instead_of_blocking_the_run(): void
    {
        $automationId = $this->automation('ABANDONED_CHECKOUT', ['config' => json_encode(['wait_minutes' => 60])]);
        $this->insertSubscribedContact('ada@example.com');
        $this->abandonedOrder('ada@example.com', minutesAgo: 90);
        $this->freezeSecond();
        [$orphanId, $runKey] = $this->draftLeftByARunInThisSlot($automationId, minutesAgo: 11);

        $this->runAutomation($automationId);

        $this->assertNull(DB::table('crm_campaigns')->where('id', $orphanId)->first());
        $this->assertSame([$runKey, 'SENDING'], [$this->campaigns($automationId)->sole()->automation_run_key, $this->campaigns($automationId)->sole()->status]);
        $this->assertSame(['ada@example.com'], $this->recipientEmails((int) $this->campaigns($automationId)->sole()->id));
    }

    public function test_a_draft_left_behind_by_a_crashed_run_in_an_earlier_slot_is_removed(): void
    {
        $automationId = $this->automation('ABANDONED_CHECKOUT', ['config' => json_encode(['wait_minutes' => 60])]);
        $crashedId = $this->insertCampaign(['automation_id' => $automationId, 'automation_run_key' => 'abandon:'.$this->eventId.':202705101130', 'created_at' => now()->subMinutes(30)]);
        $manualDraftId = $this->insertCampaign(['created_at' => now()->subMinutes(30)]);

        $this->runAutomation($automationId);

        $this->assertNull(DB::table('crm_campaigns')->where('id', $crashedId)->first());
        $this->assertNotNull(DB::table('crm_campaigns')->where('id', $manualDraftId)->first());
    }

    public function test_a_draft_another_run_is_still_launching_is_left_alone(): void
    {
        $automationId = $this->automation('ABANDONED_CHECKOUT', ['config' => json_encode(['wait_minutes' => 60])]);
        $this->insertSubscribedContact('ada@example.com');
        $this->abandonedOrder('ada@example.com', minutesAgo: 90);
        $this->freezeSecond();
        [$inFlightId] = $this->draftLeftByARunInThisSlot($automationId, minutesAgo: 1);

        $this->runAutomation($automationId);

        $this->assertSame([$inFlightId], $this->campaigns($automationId)->pluck('id')->map(static fn ($id) => (int) $id)->all());
        $this->assertSame('DRAFT', $this->campaigns($automationId)->sole()->status);
    }

    public static function checkoutsThatAreNotRecovered(): array
    {
        return [
            'abandoned too recently' => [['minutes_ago' => 30]],
            'abandoned before the automation was switched on' => [['minutes_ago' => 90, 'enabled_minutes_ago' => 60]],
            'abandoned more than a day ago' => [['minutes_ago' => 60 * 26, 'enabled_minutes_ago' => 60 * 48]],
            'box office order' => [['order' => ['box_office_id' => 'box_office']]],
            'payment link order' => [['order' => ['payment_link_created_at' => 'now']]],
            'order created by staff' => [['order' => ['is_manually_created' => true]]],
            'waitlist offer' => [['waitlist_offer' => true]],
            'bought later' => [['bought_later' => true]],
            'not subscribed' => [['contact_status' => 'UNSUBSCRIBED']],
            'never opted in' => [['contact_status' => 'NOT_SUBSCRIBED']],
            'bounced' => [['contact_status' => 'BOUNCED']],
            'nobody in the audience has that email' => [['no_contact' => true]],
            'event no longer live' => [['event_status' => 'DRAFT']],
            'event has no upcoming date' => [['past_event' => true]],
        ];
    }

    #[DataProvider('checkoutsThatAreNotRecovered')]
    public function test_a_checkout_outside_the_rules_is_not_recovered(array $case): void
    {
        $automationId = $this->automation('ABANDONED_CHECKOUT', [
            'config' => json_encode(['wait_minutes' => 60]),
            'enabled_at' => now()->subMinutes($case['enabled_minutes_ago'] ?? 600),
        ]);
        if (! ($case['no_contact'] ?? false)) {
            $this->insertSubscribedContact('ada@example.com', ['email_marketing_status' => $case['contact_status'] ?? 'SUBSCRIBED']);
        }
        $this->insertSubscribedContact('bystander@example.com');
        $order = $case['order'] ?? [];
        if (($order['box_office_id'] ?? null) === 'box_office') {
            $order['box_office_id'] = DB::table('box_offices')->insertGetId(['event_id' => $this->eventId, 'short_id' => 'bo_'.uniqid(), 'name' => 'Door', 'created_at' => now(), 'updated_at' => now()]);
        }
        if (($order['payment_link_created_at'] ?? null) === 'now') {
            $order['payment_link_created_at'] = now()->subHours(3);
        }
        $orderId = $this->abandonedOrder('ada@example.com', minutesAgo: $case['minutes_ago'] ?? 90, attributes: $order);
        if ($case['waitlist_offer'] ?? false) {
            $this->insertWaitlistEntry(['order_id' => $orderId, 'status' => 'OFFER_EXPIRED']);
        }
        if ($case['bought_later'] ?? false) {
            $this->insertOrder(['email' => 'ADA@example.com ', 'created_at' => now()->subMinutes(80)]);
        }
        if (isset($case['event_status'])) {
            DB::table('events')->where('id', $this->eventId)->update(['status' => $case['event_status']]);
        }
        if ($case['past_event'] ?? false) {
            DB::table('event_occurrences')->where('event_id', $this->eventId)->update(['start_date' => now()->subDay(), 'end_date' => now()->subDay()->addHours(2)]);
        }

        $this->runAutomation($automationId);

        $this->assertCount(0, $this->campaigns($automationId));
    }

    public function test_a_buyer_who_pays_before_the_recovery_email_goes_out_is_skipped(): void
    {
        Bus::fake([DispatchCampaignJob::class, SendCampaignEmailJob::class]);
        $automationId = $this->automation('ABANDONED_CHECKOUT', ['config' => json_encode(['wait_minutes' => 60])]);
        $this->insertSubscribedContact('ada@example.com');
        $this->abandonedOrder('ada@example.com', minutesAgo: 90);
        $this->runAutomation($automationId);
        $campaign = $this->campaigns($automationId)->sole();
        app(CampaignDispatchService::class)->dispatch((int) $campaign->id);

        $this->travel(1)->minutes();
        $this->insertOrder(['email' => 'ada@example.com', 'created_at' => now()]);
        app(CampaignDeliveryService::class)->deliver((int) DB::table('crm_campaign_recipients')->where('campaign_id', $campaign->id)->value('id'));

        $this->assertSame('SKIPPED', DB::table('crm_campaign_recipients')->where('campaign_id', $campaign->id)->value('status'));
        $this->assertCount(0, app('mail.manager')->mailer()->getSymfonyTransport()->messages());
    }

    public function test_a_reminder_goes_to_subscribers_without_a_ticket_at_ten_in_the_morning_event_time_days_before(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['timezone' => 'America/New_York']);
        $startsAt = Carbon::parse('2027-05-20 20:00:00', 'America/New_York');
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['start_date' => $startsAt->copy()->utc(), 'end_date' => $startsAt->copy()->addHours(2)->utc()]);
        $automationId = $this->automation('BEFORE_EVENT', ['config' => json_encode(['days_before' => 3, 'occurrence_audience' => 'not_purchased'])]);
        $this->insertSubscribedContact('fan@example.com');
        $this->holder('holder@example.com');
        $sendAt = Carbon::parse('2027-05-17 10:00:00', 'America/New_York')->utc();

        $this->travelTo($sendAt->copy()->subMinute());
        $this->runAutomation($automationId);
        $this->assertCount(0, $this->campaigns($automationId));

        $this->travelTo($sendAt->copy()->addMinutes(4));
        $this->runAutomation($automationId);
        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(["before:{$this->occurrenceId}", $this->occurrenceId, true], [$campaign->automation_run_key, (int) $campaign->event_occurrence_id, (bool) $campaign->excludes_occurrence_holders]);
        $this->assertSame(['fan@example.com'], $this->recipientEmails((int) $campaign->id));
    }

    public function test_a_reminder_for_ticket_holders_only_reaches_people_with_a_ticket_for_that_date(): void
    {
        $automationId = $this->automation('BEFORE_EVENT', ['config' => json_encode(['days_before' => 1, 'occurrence_audience' => 'purchased'])]);
        $this->insertSubscribedContact('fan@example.com');
        $this->holder('holder@example.com');
        $this->travelToReminderTime(daysBefore: 1);

        $this->runAutomation($automationId);

        $this->assertSame(['holder@example.com'], $this->recipientEmails((int) $this->campaigns($automationId)->sole()->id));
    }

    public static function remindersThatAreNotSent(): array
    {
        return [
            'send time was before the automation was switched on' => [['enabled_after_send_time' => true]],
            'date was added after its send time' => [['created_after_send_time' => true]],
            'send time passed more than six hours ago' => [['late_hours' => 7]],
            'date was cancelled' => [['occurrence_status' => 'CANCELLED']],
            'event is not live' => [['event_status' => 'DRAFT']],
        ];
    }

    #[DataProvider('remindersThatAreNotSent')]
    public function test_a_reminder_outside_the_rules_is_not_sent(array $case): void
    {
        $this->insertSubscribedContact('fan@example.com');
        $sendAt = $this->travelToReminderTime(daysBefore: 2, lateHours: $case['late_hours'] ?? 0);
        $automationId = $this->automation('BEFORE_EVENT', [
            'config' => json_encode(['days_before' => 2, 'occurrence_audience' => 'not_purchased']),
            'enabled_at' => ($case['enabled_after_send_time'] ?? false) ? $sendAt->copy()->addMinute() : now()->subDays(30),
        ]);
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update([
            'status' => $case['occurrence_status'] ?? 'ACTIVE',
            'created_at' => ($case['created_after_send_time'] ?? false) ? $sendAt->copy()->addMinute() : now()->subDays(30),
        ]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => $case['event_status'] ?? 'LIVE']);

        $this->runAutomation($automationId);

        $this->assertCount(0, $this->campaigns($automationId));
    }

    public function test_a_due_reminder_is_not_crowded_out_by_many_earlier_dates_that_are_not_due(): void
    {
        $automationId = $this->automation('BEFORE_EVENT', ['config' => json_encode(['days_before' => 3, 'occurrence_audience' => 'not_purchased'])]);
        $this->insertSubscribedContact('fan@example.com');
        $this->travelToReminderTime(daysBefore: 3);
        foreach (range(1, 110) as $minute) {
            $earlierId = $this->insertOccurrence($this->eventId, now()->addDay()->addMinutes($minute));
            DB::table('event_occurrences')->where('id', $earlierId)->update(['created_at' => now()->subDays(30)]);
        }

        $this->runAutomation($automationId);

        $this->assertSame(["before:{$this->occurrenceId}"], $this->campaigns($automationId)->pluck('automation_run_key')->all());
    }

    public function test_a_due_reminder_is_not_crowded_out_by_many_dates_in_the_same_window_that_are_not_due(): void
    {
        $automationId = $this->automation('BEFORE_EVENT', ['config' => json_encode(['days_before' => 3, 'occurrence_audience' => 'not_purchased'])]);
        $this->insertSubscribedContact('fan@example.com');
        $this->travelToReminderTime(daysBefore: 3);
        $startsAt = Carbon::parse(DB::table('event_occurrences')->where('id', $this->occurrenceId)->value('start_date'), 'UTC');
        foreach (range(1, 110) as $minute) {
            $addedTooLateId = $this->insertOccurrence($this->eventId, $startsAt->copy()->subMinutes($minute));
            DB::table('event_occurrences')->where('id', $addedTooLateId)->update(['created_at' => now()]);
        }

        $this->runAutomation($automationId);

        $this->assertSame(["before:{$this->occurrenceId}"], $this->campaigns($automationId)->pluck('automation_run_key')->all());
    }

    public function test_more_due_reminders_than_one_run_sends_are_finished_by_the_next_run(): void
    {
        $automationId = $this->automation('BEFORE_EVENT', ['config' => json_encode(['days_before' => 3, 'occurrence_audience' => 'purchased'])]);
        $this->travelToReminderTime(daysBefore: 3);
        $startsAt = Carbon::parse(DB::table('event_occurrences')->where('id', $this->occurrenceId)->value('start_date'), 'UTC');
        foreach (range(1, 24) as $minute) {
            $dueId = $this->insertOccurrence($this->eventId, $startsAt->copy()->addMinutes($minute));
            DB::table('event_occurrences')->where('id', $dueId)->update(['created_at' => now()->subDays(30)]);
        }

        $this->runAutomation($automationId);
        $this->assertCount(20, $this->campaigns($automationId));

        $this->travel(15)->minutes();
        $this->runAutomation($automationId);
        $this->assertCount(25, $this->campaigns($automationId)->unique('automation_run_key'));
    }

    public function test_reminders_for_a_recurring_event_reach_a_non_buyer_at_most_once_a_week(): void
    {
        $automationId = $this->automation('BEFORE_EVENT', ['config' => json_encode(['days_before' => 1, 'occurrence_audience' => 'not_purchased'])]);
        $this->insertSubscribedContact('fan@example.com');
        $this->travelToReminderTime(daysBefore: 1);
        $this->runAutomation($automationId);
        $firstCampaign = $this->campaigns($automationId)->sole();
        app(CampaignDispatchService::class)->dispatch((int) $firstCampaign->id);
        DB::table('crm_campaign_recipients')->where('campaign_id', $firstCampaign->id)->update(['status' => 'SENT', 'sent_at' => now()]);

        $nextDate = $this->insertOccurrence($this->eventId, Carbon::parse(DB::table('event_occurrences')->where('id', $this->occurrenceId)->value('start_date'), 'UTC')->addDays(2));
        DB::table('event_occurrences')->where('id', $nextDate)->update(['created_at' => now()->subDays(30)]);
        $this->travel(2)->days();
        $this->runAutomation($automationId);

        $second = $this->campaigns($automationId)->where('automation_run_key', "before:$nextDate")->sole();
        $this->assertSame(['CANCELLED', 'AUTOMATION_SKIPPED', 0], [$second->status, $second->status_reason, (int) $second->recipients_count]);
        $this->assertSame(1, DB::table('crm_campaign_recipients')->where('email', 'fan@example.com')->count());
    }

    public function test_a_reminder_for_ticket_holders_that_reaches_nobody_is_kept_as_skipped(): void
    {
        $automationId = $this->automation('BEFORE_EVENT', ['config' => json_encode(['days_before' => 1, 'occurrence_audience' => 'purchased'])]);
        $this->insertSubscribedContact('fan@example.com');
        $this->travelToReminderTime(daysBefore: 1);

        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(['CANCELLED', 'AUTOMATION_SKIPPED'], [$campaign->status, $campaign->status_reason]);
    }

    public function test_a_reminder_for_people_without_a_ticket_that_reaches_nobody_is_kept_as_skipped_instead_of_tried_again(): void
    {
        $automationId = $this->automation('BEFORE_EVENT', ['config' => json_encode(['days_before' => 1, 'occurrence_audience' => 'not_purchased'])]);
        $this->travelToReminderTime(daysBefore: 1);

        $this->runAutomation($automationId);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(['CANCELLED', 'AUTOMATION_SKIPPED'], [$campaign->status, $campaign->status_reason]);
    }

    public function test_a_reminder_approved_after_its_date_started_or_was_cancelled_is_withdrawn_instead_of_sent(): void
    {
        config(['app.saas_mode_enabled' => true]);
        $automationId = $this->automation('BEFORE_EVENT', ['config' => json_encode(['days_before' => 1, 'occurrence_audience' => 'purchased'])]);
        $this->holder('holder@example.com');
        $this->travelToReminderTime(daysBefore: 1);
        $this->runAutomation($automationId);
        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame('PENDING_REVIEW', $campaign->status);
        $launch = app(CampaignLaunchService::class);
        $find = fn () => app(CrmCampaignRepositoryInterface::class)->findById((int) $campaign->id);

        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['start_date' => now()->addHours(2), 'end_date' => now()->addHours(4)]);
        $this->travel(3)->hours();

        try {
            $launch->approve($find(), Carbon::parse($campaign->updated_at));
            $this->fail('A reminder for a date that already started was approved.');
        } catch (CampaignReviewExpiredException) {
        }

        $withdrawn = $this->campaigns($automationId)->sole();
        $this->assertSame(['CANCELLED', 'REVIEW_EXPIRED'], [$withdrawn->status, $withdrawn->status_reason]);
        $this->assertSame(['SKIPPED'], DB::table('crm_campaign_recipients')->where('campaign_id', $campaign->id)->pluck('status')->all());
        $this->assertSame(
            ['code' => 'REVIEW_EXPIRED', 'params' => []],
            json_decode(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'), true),
        );
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_someone_who_joins_a_segment_while_this_quarter_hours_email_is_already_out_is_emailed_by_the_next_one(): void
    {
        $automationId = $this->automation('SEGMENT_ENTERED', ['preset_segment' => 'subscribers']);
        $this->runAutomation($automationId);
        $this->travel(15)->minutes();
        $firstId = $this->insertSubscribedContact('first@example.com');
        $this->runAutomation($automationId);
        $this->insertSubscribedContact('second@example.com');

        $this->runAutomation($automationId);

        $this->assertSame([$firstId], $this->members($automationId));
        $this->assertSame(['first@example.com'], $this->recipientEmails((int) $this->campaigns($automationId)->sole()->id));

        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $this->assertSame(['second@example.com'], $this->recipientEmails((int) $this->campaigns($automationId)->last()->id));
    }

    public function test_more_people_joining_a_segment_than_the_account_may_email_at_once_are_emailed_over_several_runs(): void
    {
        $this->useRecipientLimit(2);
        $automationId = $this->automation('SEGMENT_ENTERED', ['preset_segment' => 'subscribers']);
        $this->runAutomation($automationId);
        foreach (['a', 'b', 'c'] as $name) {
            $this->insertSubscribedContact($name.'@example.com');
        }

        $this->travel(15)->minutes();
        $this->runAutomation($automationId);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $this->assertSame([2, 1], $this->campaigns($automationId)->pluck('recipients_count')->map(static fn ($count) => (int) $count)->all());
        $this->assertNull(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'));
        $this->assertCount(3, $this->members($automationId));
    }

    public function test_more_abandoned_checkouts_than_the_account_may_email_at_once_are_recovered_over_several_runs(): void
    {
        $this->useRecipientLimit(2);
        $automationId = $this->automation('ABANDONED_CHECKOUT', ['config' => json_encode(['wait_minutes' => 60])]);
        foreach (['a', 'b', 'c'] as $name) {
            $this->insertSubscribedContact($name.'@example.com');
            $this->abandonedOrder($name.'@example.com', minutesAgo: 90);
        }

        $this->runAutomation($automationId);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $this->assertSame([2, 1], $this->campaigns($automationId)->pluck('recipients_count')->map(static fn ($count) => (int) $count)->all());
        $this->assertNull(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'));
    }

    public function test_only_people_who_join_a_segment_after_it_is_switched_on_are_emailed_and_only_once(): void
    {
        $tagId = $this->insertTag('VIP');
        $existing = $this->insertSubscribedContact('existing@example.com');
        DB::table('crm_contact_tags')->insert(['contact_id' => $existing, 'tag_id' => $tagId, 'created_at' => now()]);
        $segmentId = DB::table('crm_segments')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => 'VIPs',
            'definition' => json_encode(['match' => 'all', 'rules' => [['field' => 'tag', 'operator' => 'in', 'value' => [$tagId]]]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $automationId = $this->automation('SEGMENT_ENTERED', ['segment_id' => $segmentId, 'preset_segment' => null]);

        $this->runAutomation($automationId);
        $this->assertCount(0, $this->campaigns($automationId));
        $this->assertSame([$existing], $this->members($automationId));

        $newcomer = $this->insertSubscribedContact('newcomer@example.com');
        DB::table('crm_contact_tags')->insert(['contact_id' => $newcomer, 'tag_id' => $tagId, 'created_at' => now()]);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(['newcomer@example.com'], $this->recipientEmails((int) $campaign->id));
        app(CampaignDispatchService::class)->dispatch((int) $campaign->id);
        DB::table('crm_campaign_recipients')->where('campaign_id', $campaign->id)->update(['status' => 'SENT', 'sent_at' => now()]);

        DB::table('crm_contact_tags')->where('contact_id', $newcomer)->delete();
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);
        $this->assertSame([$existing, $newcomer], $this->members($automationId));
        DB::table('crm_contact_tags')->insert(['contact_id' => $newcomer, 'tag_id' => $tagId, 'created_at' => now()]);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $this->assertSame(0, DB::table('crm_campaign_recipients')->whereIn('campaign_id', $this->campaigns($automationId)->pluck('id'))->where('email', 'newcomer@example.com')->where('campaign_id', '!=', $campaign->id)->count());
    }

    public function test_more_people_joining_a_segment_than_one_run_sends_to_are_finished_by_the_next_run(): void
    {
        $automationId = $this->automation('SEGMENT_ENTERED', ['preset_segment' => 'subscribers']);
        $this->runAutomation($automationId);
        $joining = SegmentEnteredTrigger::MAX_ENTRANTS_PER_RUN + 1;
        foreach (array_chunk(range(1, $joining), 1000) as $numbers) {
            $this->insertContactRows(array_map(fn (int $number) => [
                'account_id' => $this->accountId,
                'organizer_id' => $this->organizerId,
                'email' => "joiner$number@example.com",
                'source' => 'MANUAL',
                'email_marketing_status' => 'SUBSCRIBED',
                'created_at' => now(),
                'updated_at' => now(),
            ], $numbers));
        }
        DB::statement("
            INSERT INTO crm_consent_events (organizer_id, contact_id, email, action, source_type, occurred_at)
            SELECT organizer_id, id, email, 'OPT_IN', 'MANUAL', now() FROM crm_contacts WHERE organizer_id = ? AND email LIKE 'joiner%'
        ", [$this->organizerId]);

        $this->travel(15)->minutes();
        $this->runAutomation($automationId);
        $this->assertSame(SegmentEnteredTrigger::MAX_ENTRANTS_PER_RUN, (int) $this->campaigns($automationId)->sole()->recipients_count);
        $this->assertCount(SegmentEnteredTrigger::MAX_ENTRANTS_PER_RUN, $this->members($automationId));

        $this->travel(15)->minutes();
        $this->runAutomation($automationId);
        $this->assertSame([SegmentEnteredTrigger::MAX_ENTRANTS_PER_RUN, 1], $this->campaigns($automationId)->pluck('recipients_count')->map(static fn ($count) => (int) $count)->all());
        $this->assertCount($joining, $this->members($automationId));
    }

    public function test_entrants_of_a_run_that_launched_but_crashed_before_recording_them_are_not_emailed_again(): void
    {
        $automationId = $this->automation('SEGMENT_ENTERED', ['preset_segment' => 'subscribers']);
        $this->runAutomation($automationId);
        $fanId = $this->insertSubscribedContact('fan@example.com');
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);
        DB::table('crm_automation_memberships')->where('automation_id', $automationId)->where('contact_id', $fanId)->delete();

        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $this->assertSame(['fan@example.com'], $this->recipientEmails((int) $this->campaigns($automationId)->sole()->id));
        $this->assertSame([$fanId], $this->members($automationId));
    }

    public function test_changing_the_segment_rules_starts_afresh_without_emailing_people_who_already_match(): void
    {
        $automationId = $this->automation('SEGMENT_ENTERED', ['preset_segment' => 'subscribers']);
        $adaId = $this->insertSubscribedContact('ada@example.com');
        $this->runAutomation($automationId);
        $this->assertSame([$adaId], $this->members($automationId));

        DB::table('crm_automations')->where('id', $automationId)->update(['preset_segment' => 'repeat_buyers']);
        $this->insertSubscribedContact('fan@example.com', ['orders_count' => 3, 'events_count' => 3]);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $this->assertCount(0, $this->campaigns($automationId));
        $this->assertCount(1, $this->members($automationId));
    }

    public function test_people_who_joined_while_sending_was_blocked_are_emailed_on_a_later_run(): void
    {
        $automationId = $this->automation('SEGMENT_ENTERED', ['preset_segment' => 'subscribers']);
        $this->runAutomation($automationId);
        $this->insertSubscribedContact('fan@example.com');
        $gate = Mockery::mock(CampaignSendGate::class, [
            app(AccountRepositoryInterface::class),
            app(CrmCampaignRepositoryInterface::class),
            app(CrmContactRepositoryInterface::class),
            app(MessagingEligibilityService::class),
            app('config'),
            app(CampaignSupportInbox::class),
        ])->makePartial();
        $gate->shouldReceive('assertAccountMaySend')->andThrow(new CampaignNotSendableException('Too many.', CampaignNotSendableException::REASON_TOO_MANY_RECIPIENTS, ['count' => 3, 'max' => 2]));
        $this->app->instance(CampaignSendGate::class, $gate);
        $this->travel(15)->minutes();

        $this->runAutomation($automationId);

        $this->assertCount(0, $this->campaigns($automationId));
        $this->assertSame([], $this->members($automationId));
        $this->assertSame(
            ['code' => 'TOO_MANY_RECIPIENTS', 'params' => ['count' => 3, 'max' => 2]],
            json_decode(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'), true),
        );
    }

    private function automation(string $trigger, array $attributes = []): int
    {
        $id = DB::table('crm_automations')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => $trigger,
            'trigger' => $trigger,
            'is_enabled' => true,
            'enabled_at' => now()->subDays(30),
            'subject' => 'About {{ event_title }}',
            'body' => '<p>Hello</p>',
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);

        if ($trigger === 'SEGMENT_ENTERED') {
            DB::table('crm_automations')->where('id', $id)->update(['segment_definition_hash' => null]);
        }

        return $id;
    }

    private function useRecipientLimit(int $maxRecipients): void
    {
        DB::table('accounts')->where('id', $this->accountId)->update([
            'account_messaging_tier_id' => DB::table('account_messaging_tiers')->insertGetId([
                'name' => 'Test tier '.uniqid(),
                'max_messages_per_24h' => 50,
                'max_recipients_per_message' => $maxRecipients,
                'links_allowed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ]);
    }

    private function abandonedOrder(string $email, int $minutesAgo, array $attributes = []): int
    {
        return $this->insertOrder([
            'email' => $email,
            'status' => 'RESERVED',
            'reserved_until' => now()->subMinutes($minutesAgo),
            'created_at' => now()->subMinutes($minutesAgo + 15),
            'updated_at' => now()->subMinutes($minutesAgo + 15),
            ...$attributes,
        ]);
    }

    private function holder(string $email): void
    {
        $orderId = $this->insertOrder(['email' => $email, 'opted_into_marketing_at' => now()->subDays(5)]);
        $this->insertAttendee($orderId);
        app(ContactSyncService::class)->projectOrders($this->accountId, $this->organizerId, [$orderId]);
    }

    private function travelToReminderTime(int $daysBefore, int $lateHours = 0): Carbon
    {
        $occurrence = DB::table('event_occurrences')->where('id', $this->occurrenceId)->first();
        $sendAt = BeforeEventTrigger::reminderSendTime($occurrence->start_date, 'UTC', $daysBefore);
        $this->travelTo($sendAt->copy()->addMinutes(5)->addHours($lateHours));

        return $sendAt;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function draftLeftByARunInThisSlot(int $automationId, int $minutesAgo): array
    {
        $this->runAutomation($automationId);
        $campaign = $this->campaigns($automationId)->sole();
        DB::table('crm_campaign_recipients')->where('campaign_id', $campaign->id)->delete();
        DB::table('crm_campaigns')->where('id', $campaign->id)->update([
            'status' => 'DRAFT',
            'launched_at' => null,
            'sent_at' => null,
            'recipients_count' => 0,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);

        return [(int) $campaign->id, $campaign->automation_run_key];
    }

    private function runAutomation(int $automationId): void
    {
        app(AutomationRunner::class)->run(app(CrmAutomationRepositoryInterface::class)->findById($automationId));
    }

    private function campaigns(int $automationId): Collection
    {
        return DB::table('crm_campaigns')->where('automation_id', $automationId)->orderBy('id')->get();
    }

    /**
     * @return string[]
     */
    private function recipientEmails(int $campaignId): array
    {
        return DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->orderBy('email')->pluck('email')->all();
    }

    /**
     * @return int[]
     */
    private function members(int $automationId): array
    {
        return DB::table('crm_automation_memberships')->where('automation_id', $automationId)->orderBy('contact_id')->pluck('contact_id')->map(static fn ($id) => (int) $id)->all();
    }
}
