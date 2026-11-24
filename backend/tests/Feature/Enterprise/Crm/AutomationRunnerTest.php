<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPendingReviewJob;
use HiEvents\Enterprise\Crm\Jobs\RunAutomationJob;
use HiEvents\Enterprise\Crm\Jobs\RunAutomationsJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationRunner;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationFailureDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\EventPublishedTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignMailFactory;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSendGate;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\CampaignSupportInbox;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncService;
use HiEvents\Jobs\Event\EventSpamCheckJob;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use HiEvents\Services\Domain\Message\MessagingEligibilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class AutomationRunnerTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(12, 0));
        config(['app.saas_mode_enabled' => false, 'app.event_spam_check_enabled' => false]);
        $this->setUpCrmOrganizer();
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);

        $history = ['first_seen_at' => now()->subDays(200), 'last_order_at' => now()->subDays(10)];
        $this->insertSubscribedContact('ada@example.com', ['events_count' => 5, 'orders_count' => 5, ...$history]);
        $this->insertSubscribedContact('grace@example.com', ['events_count' => 1, 'orders_count' => 1, ...$history]);
        $this->insertContact('quiet@example.com', ['email_marketing_status' => 'UNSUBSCRIBED', 'events_count' => 5, 'orders_count' => 5, 'first_seen_at' => now()->subDays(400), 'last_order_at' => now()->subDays(300)]);
    }

    public function test_a_newly_published_event_is_announced_once_to_everyone_subscribed(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', [
            'subject' => ' New: {{ event_title }} on {{event_date}} by {{ organizer_name }} ',
            'body' => '<p>{{ event_title }} / {{ event_date }} / {{ organizer_name }}</p>',
        ]);
        DB::table('organizers')->where('id', $this->organizerId)->update(['name' => 'Jazz & <i>Blues</i>']);
        $startsAt = now()->addDays(20)->setTime(23, 30);
        $eventId = $this->liveEvent('Rock & <b>Roll</b>', $startsAt, ['timezone' => 'Pacific/Auckland']);
        $eventDate = Carbon::parse($startsAt->toDateTimeString(), 'UTC')->setTimezone('Pacific/Auckland')->isoFormat('LL');

        $this->runAutomation($automationId);
        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(
            [$this->accountId, $this->organizerId, $eventId, "event:$eventId", 'Rock & <b>Roll</b>', 'SENDING', 2],
            [(int) $campaign->account_id, (int) $campaign->organizer_id, (int) $campaign->event_id, $campaign->automation_run_key, $campaign->name, $campaign->status, (int) $campaign->recipients_count],
        );
        $this->assertSame('New: Rock & <b>Roll</b> on {{event_date}} by Jazz & <i>Blues</i>', $campaign->subject);
        $this->assertSame(
            '<p>'.e('Rock & <b>Roll</b>').' / {{ event_date }} / '.e('Jazz & <i>Blues</i>').'</p>',
            $campaign->body,
        );
        $mail = $this->renderedMail((int) $campaign->id);
        $this->assertSame("New: Rock & <b>Roll</b> on $eventDate by Jazz & <i>Blues</i>", $mail['subject']);
        $this->assertStringContainsString(e('Rock & <b>Roll</b>').' / '.e($eventDate).' / ', $mail['html']);
        $this->assertNotSame(now()->addDays(20)->isoFormat('LL'), $eventDate);
        $this->assertSame(['ada@example.com', 'grace@example.com'], $this->recipientEmails((int) $campaign->id));
        $this->assertNull($campaign->promo_code_id);
        $this->assertSame(0, DB::table('promo_codes')->count());
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
        Bus::assertDispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->campaignId === (int) $campaign->id);
    }

    public function test_an_event_whose_tickets_are_all_public_is_announced_too(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $eventId = $this->liveEvent('Open Air', now()->addDays(20), withHiddenTicket: false);

        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(
            ['SENDING', null, $eventId, null, 2],
            [$campaign->status, $campaign->status_reason, (int) $campaign->event_id, $campaign->promo_code_id, (int) $campaign->recipients_count],
        );
        $this->assertSame(0, DB::table('promo_codes')->count());
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
    }

    public function test_an_announcement_whose_run_crashed_after_creating_its_draft_is_still_sent(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $eventId = $this->liveEvent('Open Air', now()->addDays(20), withHiddenTicket: false);
        $orphanId = $this->insertCampaign(['automation_id' => $automationId, 'automation_run_key' => 'event:'.$eventId, 'created_at' => now()->subMinutes(11)]);

        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertNotSame($orphanId, (int) $campaign->id);
        $this->assertSame(['event:'.$eventId, 'SENDING'], [$campaign->automation_run_key, $campaign->status]);
    }

    public function test_an_announcement_draft_another_run_is_still_launching_is_left_alone(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $eventId = $this->liveEvent('Open Air', now()->addDays(20), withHiddenTicket: false);
        $inFlightId = $this->insertCampaign(['automation_id' => $automationId, 'automation_run_key' => 'event:'.$eventId, 'created_at' => now()->subMinute()]);

        $this->runAutomation($automationId);

        $this->assertSame([[$inFlightId, 'DRAFT']], $this->campaigns($automationId)->map(static fn ($row) => [(int) $row->id, $row->status])->all());
    }

    public static function ticketVisibilities(): array
    {
        return [
            'event with a hidden ticket' => [true],
            'event with only public tickets' => [false],
        ];
    }

    #[DataProvider('ticketVisibilities')]
    public function test_an_announcement_is_delivered_with_a_plain_link_to_the_event_and_no_promo_code(bool $withHiddenTicket): void
    {
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', ['subject' => 'Just announced: {{ event_title }}', 'body' => '<p>Come to {{ event_title }}</p>']);
        $eventId = $this->liveEvent('Summer Party', now()->addDays(20), withHiddenTicket: $withHiddenTicket);

        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame('SENT', $campaign->status);
        $messages = $this->sentEmails();
        $this->assertSame(['ada@example.com', 'grace@example.com'], $messages->keys()->sort()->values()->all());
        $this->assertSame('Just announced: Summer Party', $messages['ada@example.com']->getSubject());
        $this->assertStringContainsString('Come to Summer Party', $messages['ada@example.com']->getHtmlBody());
        $this->assertStringContainsString("/event/$eventId/summer-party", $messages['ada@example.com']->getHtmlBody());
        $this->assertStringContainsString('Get tickets', $messages['ada@example.com']->getHtmlBody());
        $this->assertStringNotContainsString('promo_code', $messages['ada@example.com']->getHtmlBody());
        $this->assertSame([$eventId, null], [(int) $campaign->event_id, $campaign->promo_code_id]);
        $this->assertSame(0, DB::table('promo_codes')->count());
    }

    public function test_the_announcement_respects_the_automations_segment_or_preset(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', ['preset_segment' => 'repeat_buyers']);
        $firstEventId = $this->liveEvent('Summer Party', now()->addDays(20));

        $this->runAutomation($automationId);

        DB::table('crm_automations')->where('id', $automationId)->update([
            'segment_id' => $this->insertSavedSegment([['field' => 'events_count', 'operator' => 'eq', 'value' => 1]]),
        ]);
        $secondEventId = $this->liveEvent('Autumn Party', now()->addDays(30));
        $this->runAutomation($automationId);

        $campaigns = $this->campaigns($automationId)->keyBy('automation_run_key');
        $byPreset = $campaigns["event:$firstEventId"];
        $bySegment = $campaigns["event:$secondEventId"];
        $this->assertCount(2, $campaigns);
        $this->assertSame([null, 'repeat_buyers'], [$byPreset->segment_id, $byPreset->preset_segment]);
        $this->assertSame([(int) DB::table('crm_automations')->where('id', $automationId)->value('segment_id'), null], [(int) $bySegment->segment_id, $bySegment->preset_segment]);
        $this->assertSame(['ada@example.com'], $this->recipientEmails((int) $byPreset->id));
        $this->assertSame(['grace@example.com'], $this->recipientEmails((int) $bySegment->id));
    }

    public static function eventsThatAreNotAnnounced(): array
    {
        return [
            'draft event' => [['status' => 'DRAFT'], 20],
            'event waiting for manual review' => [['status' => 'PENDING_MANUAL_REVIEW'], 20],
            'archived event' => [['status' => 'ARCHIVED'], 20],
            'event that was already live when the automation was switched on' => [['baseline' => true], 20],
            'event edited less than thirty minutes ago' => [['updated_minutes_ago' => 29], 20],
            'event with no future date' => [[], -1],
            'event whose only future date was deleted' => [['occurrence_deleted' => true], 20],
            'event whose only future date was cancelled' => [['occurrence_cancelled' => true], 20],
            'deleted event' => [['deleted' => true], 20],
            'event of another organizer' => [['foreign' => true], 20],
        ];
    }

    #[DataProvider('eventsThatAreNotAnnounced')]
    public function test_an_event_that_is_not_newly_live_is_never_announced(array $event, int $startsInDays): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $organizerId = ($event['foreign'] ?? false) ? $this->createOrganizer($this->accountId, 'Other Organizer') : $this->organizerId;
        $eventId = $this->liveEvent('Summer Party', now()->addDays($startsInDays), organizerId: $organizerId);
        DB::table('events')->where('id', $eventId)->update([
            'status' => $event['status'] ?? 'LIVE',
            'updated_at' => now()->subMinutes($event['updated_minutes_ago'] ?? 45),
            'deleted_at' => ($event['deleted'] ?? false) ? now() : null,
        ]);
        if ($event['occurrence_deleted'] ?? false) {
            DB::table('event_occurrences')->where('event_id', $eventId)->update(['deleted_at' => now()]);
            $this->insertOccurrence($eventId, now()->subDays(3));
        }
        if ($event['occurrence_cancelled'] ?? false) {
            DB::table('event_occurrences')->where('event_id', $eventId)->update(['status' => 'CANCELLED']);
            $this->insertOccurrence($eventId, now()->subDays(3));
        }
        if ($event['baseline'] ?? false) {
            DB::table('crm_automations')->where('id', $automationId)->update(['baseline_event_ids' => json_encode([$eventId])]);
        }

        $this->runAutomation($automationId);

        $this->assertCount(0, $this->campaigns($automationId));
        Bus::assertNothingDispatched();
    }

    public function test_a_draft_created_before_switching_on_is_announced_once_published_but_an_event_already_live_is_not(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $alreadyLiveId = $this->liveEvent('Already Live', now()->addDays(20));
        $draftId = $this->liveEvent('Old Draft', now()->addDays(25));
        $newId = $this->liveEvent('Brand New', now()->addDays(30));
        DB::table('events')->whereIn('id', [$alreadyLiveId, $draftId])->update(['created_at' => now()->subDays(30)]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', ['baseline_event_ids' => json_encode([$alreadyLiveId])]);

        $this->runAutomation($automationId);
        $this->runAutomation($automationId);

        $this->assertSame(
            ["event:$draftId" => 'Old Draft', "event:$newId" => 'Brand New'],
            $this->campaigns($automationId)->pluck('name', 'automation_run_key')->all(),
        );
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 2);
    }

    public function test_the_settle_window_edges_are_inclusive_for_the_last_edit_and_exclusive_for_the_start(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', ['enabled_at' => now()->subHours(2)]);
        $eventId = $this->liveEvent('Summer Party', now()->addDays(20));
        $startedAtTheSettleTimeId = $this->liveEvent('Already Under Way', now()->subMinutes(EventPublishedTrigger::PUBLISH_SETTLE_MINUTES));
        $startedJustAfterId = $this->liveEvent('Just Started', now()->subMinutes(EventPublishedTrigger::PUBLISH_SETTLE_MINUTES)->addSecond());
        DB::table('events')->where('id', $eventId)->update(['created_at' => now()->subYear(), 'updated_at' => now()->subMinutes(EventPublishedTrigger::PUBLISH_SETTLE_MINUTES)]);

        $this->runAutomation($automationId);

        $this->assertSame(["event:$eventId", "event:$startedJustAfterId"], $this->campaigns($automationId)->pluck('automation_run_key')->all());
        $this->assertNotContains("event:$startedAtTheSettleTimeId", $this->campaigns($automationId)->pluck('automation_run_key')->all());
    }

    public static function spamCheckOutcomes(): array
    {
        return [
            'spam checks on, never checked' => [true, null, false],
            'spam checks on, flagged' => [true, 'FLAGGED', false],
            'spam checks on, confirmed spam' => [true, 'CONFIRMED_SPAM', false],
            'spam checks on, clean' => [true, 'CLEAN', true],
            'spam checks on, approved by a reviewer' => [true, 'APPROVED', true],
            'spam checks off, never checked' => [false, null, true],
            'spam checks off, flagged' => [false, 'FLAGGED', true],
        ];
    }

    #[DataProvider('spamCheckOutcomes')]
    public function test_with_spam_checks_on_an_event_is_only_announced_once_it_has_passed_one(bool $spamChecksEnabled, ?string $checkStatus, bool $announced): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->spamChecksAre($spamChecksEnabled);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $eventId = $this->liveEvent('Summer Party', now()->addDays(20));
        $otherEventId = $this->liveEvent('Other Party', now()->addDays(20), organizerId: $this->createOrganizer($this->accountId, 'Other Organizer'));
        $this->insertSpamCheck($otherEventId, 'CLEAN');
        if ($checkStatus !== null) {
            $this->insertSpamCheck($eventId, $checkStatus);
        }

        $this->runAutomation($automationId);

        $this->assertSame($announced ? ["event:$eventId"] : [], $this->campaigns($automationId)->pluck('automation_run_key')->all());
    }

    public function test_an_event_is_announced_when_spam_checks_are_switched_on_but_cannot_run(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        config(['app.saas_mode_enabled' => false, 'app.event_spam_check_enabled' => true, 'ai.providers.anthropic.key' => 'key']);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $eventId = $this->liveEvent('Summer Party', now()->addDays(20));

        $this->runAutomation($automationId);

        $this->assertSame(["event:$eventId"], $this->campaigns($automationId)->pluck('automation_run_key')->all());
    }

    public function test_an_event_flagged_as_spam_is_announced_after_a_reviewer_approves_it(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->spamChecksAre(true);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $eventId = $this->liveEvent('Summer Party', now()->addDays(20));
        $checkId = $this->insertSpamCheck($eventId, 'FLAGGED');

        $this->runAutomation($automationId);
        $this->assertCount(0, $this->campaigns($automationId));

        DB::table('event_spam_checks')->where('id', $checkId)->update(['status' => 'APPROVED']);
        $this->runAutomation($automationId);

        $this->assertSame("event:$eventId", $this->campaigns($automationId)->sole()->automation_run_key);
    }

    public function test_an_announcement_still_waiting_for_its_spam_check_after_two_hours_says_so_and_asks_for_the_check_again(): void
    {
        Bus::fake([DispatchCampaignJob::class, EventSpamCheckJob::class]);
        $this->spamChecksAre(true);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $eventId = $this->liveEvent('Summer Party', now()->addDays(20), ['updated_at' => now()]);

        $this->travel(EventPublishedTrigger::SPAM_CHECK_WAIT_MINUTES - 1)->minutes();
        $this->runAutomation($automationId);
        $this->assertNull(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'));
        Bus::assertNotDispatched(EventSpamCheckJob::class);

        $this->travel(1)->minutes();
        $this->runAutomation($automationId);
        $this->runAutomation($automationId);
        $this->assertCount(0, $this->campaigns($automationId));
        $this->assertSame(
            ['code' => 'EVENT_AWAITING_REVIEW', 'params' => ['event_title' => 'Summer Party']],
            json_decode(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'), true),
        );
        Bus::assertDispatched(EventSpamCheckJob::class, static fn (EventSpamCheckJob $job) => $job->uniqueId() === (string) $eventId);

        $this->insertSpamCheck($eventId, 'CLEAN');
        $this->runAutomation($automationId);
        $this->assertSame("event:$eventId", $this->campaigns($automationId)->sole()->automation_run_key);
        $this->assertNull(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'));
    }

    public function test_an_event_flagged_as_spam_is_reported_as_waiting_without_being_checked_again_until_it_is_no_longer_coming_up(): void
    {
        Bus::fake([DispatchCampaignJob::class, EventSpamCheckJob::class]);
        $this->spamChecksAre(true);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $eventId = $this->liveEvent('Summer Party', now()->addDays(20));
        $this->insertSpamCheck($eventId, 'FLAGGED');
        $this->travel(EventPublishedTrigger::SPAM_CHECK_WAIT_MINUTES)->minutes();

        $this->runAutomation($automationId);
        $this->assertSame('EVENT_AWAITING_REVIEW', AutomationFailureDTO::decode(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'))?->code);
        Bus::assertNotDispatched(EventSpamCheckJob::class);

        $this->travel(21)->days();
        $this->runAutomation($automationId);
        $this->assertNull(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'));
    }

    public function test_a_reason_a_send_was_refused_is_not_replaced_by_a_waiting_spam_check(): void
    {
        Bus::fake([DispatchCampaignJob::class, EventSpamCheckJob::class]);
        $this->spamChecksAre(true);
        $refused = (new AutomationFailureDTO('ACCOUNT_NOT_VERIFIED'))->forStorage();
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', ['last_failure_reason' => $refused]);
        $this->liveEvent('Summer Party', now()->addDays(20));
        $this->travel(EventPublishedTrigger::SPAM_CHECK_WAIT_MINUTES)->minutes();

        $this->runAutomation($automationId);

        $this->assertSame($refused, DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'));
    }

    public function test_the_announced_date_is_the_next_date_that_is_not_cancelled(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', ['subject' => '{{ event_title }} on {{ event_date }}']);
        $startsAt = now()->addDays(20)->setTime(12, 0);
        $eventId = $this->liveEvent('Summer Party', $startsAt);
        $cancelledId = $this->insertOccurrence($eventId, now()->addDays(5)->setTime(12, 0));
        DB::table('event_occurrences')->where('id', $cancelledId)->update(['status' => 'CANCELLED']);

        $this->runAutomation($automationId);

        $this->assertSame('Summer Party on '.$startsAt->isoFormat('LL'), $this->renderedMail((int) $this->campaigns($automationId)->sole()->id)['subject']);
    }

    public function test_the_event_date_is_written_in_each_recipients_language(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', ['subject' => '{{ event_title }} on {{ event_date }}']);
        $startsAt = now()->addDays(20)->setTime(12, 0);
        $this->liveEvent('Summer Party', $startsAt);

        $this->runAutomation($automationId);

        $campaignId = (int) $this->campaigns($automationId)->sole()->id;
        $this->assertSame('Summer Party on '.$startsAt->copy()->locale('de')->isoFormat('LL'), $this->renderedMail($campaignId, 'de')['subject']);
        $this->assertSame('Summer Party on '.$startsAt->copy()->locale('fr')->isoFormat('LL'), $this->renderedMail($campaignId, 'fr')['subject']);
        $this->assertNotSame($this->renderedMail($campaignId, 'de')['subject'], $this->renderedMail($campaignId, 'en')['subject']);
    }

    public function test_an_event_is_announced_once_it_has_settled_and_never_again_after_that(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $eventId = $this->liveEvent('Summer Party', now()->addDays(20));
        DB::table('events')->where('id', $eventId)->update(['status' => 'DRAFT', 'updated_at' => now()]);

        $this->runAutomation($automationId);
        $this->assertCount(0, $this->campaigns($automationId));

        DB::table('events')->where('id', $eventId)->update(['status' => 'LIVE', 'updated_at' => now()]);
        $this->travel(29)->minutes();
        $this->runAutomation($automationId);
        $this->assertCount(0, $this->campaigns($automationId));

        $this->travel(1)->minutes();
        $this->runAutomation($automationId);
        $this->assertCount(1, $this->campaigns($automationId));

        DB::table('events')->where('id', $eventId)->update(['title' => 'Summer Party (new venue)', 'updated_at' => now()]);
        $this->travel(2)->hours();
        $this->runAutomation($automationId);
        DB::table('crm_campaigns')->where('automation_id', $automationId)->update(['status' => 'CANCELLED']);
        $this->runAutomation($automationId);

        $this->assertCount(1, $this->campaigns($automationId));
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
    }

    public function test_each_newly_live_event_gets_its_own_announcement(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $firstId = $this->liveEvent('First Night', now()->addDays(20));
        $secondId = $this->liveEvent('Second Night', now()->addDays(21));

        $this->runAutomation($automationId);
        $this->runAutomation($automationId);

        $this->assertSame(
            ["event:$firstId" => 'First Night', "event:$secondId" => 'Second Night'],
            $this->campaigns($automationId)->pluck('name', 'automation_run_key')->all(),
        );
    }

    public function test_in_saas_mode_the_first_automated_campaign_waits_for_review(): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        config(['app.saas_mode_enabled' => true]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $this->liveEvent('Summer Party', now()->addDays(20));

        $this->runAutomation($automationId);
        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(['PENDING_REVIEW', 'FIRST_CAMPAIGN', 2], [$campaign->status, $campaign->status_reason, (int) $campaign->recipients_count]);
        $this->assertSame(['ada@example.com', 'grace@example.com'], $this->recipientEmails((int) $campaign->id));
        Bus::assertDispatchedTimes(NotifyCampaignPendingReviewJob::class, 1);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public static function announcementsThatCannotBeSentYet(): array
    {
        return [
            'account not verified' => ['unverified', 'SENDING'],
            'saas account not manually verified' => ['not_manually_verified', 'PENDING_REVIEW'],
            'segment deleted' => ['segment_deleted', 'SENDING'],
            'more recipients than the tier allows' => ['too_many_recipients', 'SENDING'],
            'links when the tier disallows them' => ['links', 'SENDING'],
        ];
    }

    #[DataProvider('announcementsThatCannotBeSentYet')]
    public function test_an_announcement_that_cannot_be_sent_yet_leaves_no_campaign_records_why_and_is_retried(string $case, string $statusOnceFixed): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        $automation = [];
        $tierId = null;
        switch ($case) {
            case 'unverified':
                DB::table('accounts')->where('id', $this->accountId)->update(['account_verified_at' => null]);
                break;
            case 'not_manually_verified':
                config(['app.saas_mode_enabled' => true]);
                DB::table('accounts')->where('id', $this->accountId)->update(['is_manually_verified' => false]);
                break;
            case 'segment_deleted':
                $automation = ['segment_id' => 999999999];
                break;
            case 'too_many_recipients':
                $tierId = $this->useTier(['max_recipients_per_message' => 1]);
                break;
            case 'links':
                $tierId = $this->useTier(['links_allowed' => false]);
                $automation = ['body' => '<p>Buy at <a href="https://example.com/tickets">our site</a></p>'];
                break;
        }
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', $automation);
        $eventId = $this->liveEvent('Summer Party', now()->addDays(20));

        $this->runAutomation($automationId);
        $reason = DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason');
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $this->assertCount(0, $this->campaigns($automationId));
        $this->assertNotEmpty($reason);
        $this->assertSame($reason, DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'));
        $this->assertSame(0, DB::table('crm_campaign_recipients')->where('organizer_id', $this->organizerId)->count());
        Bus::assertNothingDispatched();

        match ($case) {
            'unverified' => DB::table('accounts')->where('id', $this->accountId)->update(['account_verified_at' => now()]),
            'not_manually_verified' => DB::table('accounts')->where('id', $this->accountId)->update(['is_manually_verified' => true]),
            'segment_deleted' => DB::table('crm_automations')->where('id', $automationId)->update(['segment_id' => null]),
            'too_many_recipients' => DB::table('account_messaging_tiers')->where('id', $tierId)->update(['max_recipients_per_message' => 5000]),
            'links' => DB::table('account_messaging_tiers')->where('id', $tierId)->update(['links_allowed' => true]),
        };
        $this->runAutomation($automationId);
        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame([$statusOnceFixed, "event:$eventId", 2], [$campaign->status, $campaign->automation_run_key, (int) $campaign->recipients_count]);
        $this->assertNull(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'));
    }

    public function test_an_announcement_nobody_has_opted_in_to_is_cancelled_once_and_never_retried(): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->update(['email_marketing_status' => 'NOT_SUBSCRIBED']);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', ['last_failure_reason' => 'An earlier problem']);
        $eventId = $this->liveEvent('Summer Party', now()->addDays(20));

        $this->runAutomation($automationId);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);
        DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->where('email', 'ada@example.com')->update(['email_marketing_status' => 'SUBSCRIBED']);
        $this->travel(1)->days();
        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(
            ['CANCELLED', AutomationRunner::REASON_AUTOMATION_SKIPPED, "event:$eventId", $eventId, 0],
            [$campaign->status, $campaign->status_reason, $campaign->automation_run_key, (int) $campaign->event_id, (int) $campaign->recipients_count],
        );
        $this->assertSame([], $this->recipientEmails((int) $campaign->id));
        $this->assertNull(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'));
        Bus::assertNothingDispatched();
    }

    public function test_a_successful_run_clears_the_last_failure_reason(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED', ['last_failure_reason' => 'An earlier problem']);
        $this->liveEvent('Summer Party', now()->addDays(20));

        $this->runAutomation($automationId);

        $this->assertSame('SENDING', $this->campaigns($automationId)->sole()->status);
        $this->assertNull(DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'));
    }

    public static function triggersThatCanCrash(): array
    {
        return [
            'event announcement' => ['EVENT_PUBLISHED'],
            'daily win back' => ['WIN_BACK'],
        ];
    }

    #[DataProvider('triggersThatCanCrash')]
    public function test_an_unexpected_failure_discards_the_draft_is_rethrown_and_the_run_is_retried(string $trigger): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation($trigger);
        $this->liveEvent('Summer Party', now()->addDays(20));
        $this->eligibleContact('WIN_BACK', 'lapsed@example.com');
        $launchService = Mockery::mock(CampaignLaunchService::class);
        $launchService->shouldReceive('launch')->once()->andThrow(new RuntimeException('The database went away.'));
        $this->app->instance(CampaignLaunchService::class, $launchService);

        try {
            $this->runAutomation($automationId);
            $this->fail('The failure was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The database went away.', $exception->getMessage());
        }

        $automation = DB::table('crm_automations')->where('id', $automationId)->first();
        $this->assertCount(0, $this->campaigns($automationId));
        $this->assertSame([null, null], [$automation->last_run_at, $automation->last_failure_reason]);

        $this->app->forgetInstance(CampaignLaunchService::class);
        $this->runAutomation($automationId);

        $this->assertSame('SENDING', $this->campaigns($automationId)->sole()->status);
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
    }

    public function test_ticket_holders_are_thanked_once_after_their_event_has_ended(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('AFTER_EVENT', [
            'enabled_at' => now()->subDays(5),
            'delay_days' => 1,
            'subject' => 'Thanks for coming to {{ event_title }}',
            'body' => '<p>Thanks, from {{ organizer_name }}</p>',
            'preset_segment' => 'top_fans',
            'segment_id' => 999999999,
        ]);
        $eventId = $this->endedEvent('Winter Ball', endedHoursAgo: 30);
        $this->holder($eventId, 'holder@example.com', optedIn: true);
        $this->holder($eventId, 'silent.holder@example.com', optedIn: false);
        $this->holder($this->eventId, 'elsewhere@example.com', optedIn: true);

        $this->runAutomation($automationId);
        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $occurrence = DB::table('event_occurrences')->where('event_id', $eventId)->sole();
        $this->assertSame(
            ["occurrence:$occurrence->id", 'Winter Ball · '.substr($occurrence->start_date, 0, 10), 'Thanks for coming to Winter Ball', '<p>Thanks, from Test Organizer</p>', 'SENDING', null, (int) $occurrence->id, null, null],
            [$campaign->automation_run_key, $campaign->name, $campaign->subject, $campaign->body, $campaign->status, $campaign->event_id, (int) $campaign->event_occurrence_id, $campaign->segment_id, $campaign->preset_segment],
        );
        $this->assertEquals(
            ['match' => 'all', 'rules' => [['field' => 'ticket_event', 'operator' => 'in', 'value' => [$eventId]]]],
            json_decode($campaign->segment_definition, true),
        );
        $this->assertSame(['holder@example.com'], $this->recipientEmails((int) $campaign->id));
        $this->assertSame(0, DB::table('promo_codes')->count());
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
    }

    public function test_a_thank_you_can_use_the_date_and_link_of_the_event_it_follows_without_an_invitation_to_buy(): void
    {
        $automationId = $this->insertAutomation('AFTER_EVENT', [
            'enabled_at' => now()->subDays(5),
            'subject' => 'Thanks for coming on {{ event_date }}',
            'body' => '<p>See the photos from {{ event_title }} ({{ event_date }}): {{ event_url }}</p>',
        ]);
        $eventId = $this->endedEvent('Winter Ball', endedHoursAgo: 30);
        $this->holder($eventId, 'holder@example.com', optedIn: true);
        $occurrence = DB::table('event_occurrences')->where('event_id', $eventId)->sole();
        $eventDate = Carbon::parse($occurrence->start_date, 'UTC')->isoFormat('LL');

        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(['SENT', null], [$campaign->status, DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason')]);
        $mail = $this->sentEmails()['holder@example.com'];
        $this->assertSame('Thanks for coming on '.$eventDate, $mail->getSubject());
        $this->assertStringContainsString('Winter Ball ('.$eventDate.'): ', $mail->getHtmlBody());
        $this->assertStringContainsString('/event/'.$eventId.'/winter-ball', $mail->getHtmlBody());
        $this->assertStringNotContainsString('Get tickets', $mail->getHtmlBody());
    }

    public static function eventsThatAreNotFollowedUp(): array
    {
        return [
            'ended less than the delay ago' => [['ended_hours_ago' => 23]],
            'ended more than seven days before the delay' => [['ended_hours_ago' => 24 * 8 + 1, 'enabled_days_ago' => 30]],
            'ended before the automation was enabled' => [['ended_hours_ago' => 49, 'enabled_days_ago' => 2]],
            'draft event' => [['status' => 'DRAFT']],
            'event waiting for manual review' => [['status' => 'PENDING_MANUAL_REVIEW']],
            'event whose only date was cancelled' => [['cancelled' => true]],
            'deleted event' => [['deleted' => true]],
            'event of another organizer' => [['foreign' => true]],
            'event already followed up' => [['handled' => true]],
        ];
    }

    #[DataProvider('eventsThatAreNotFollowedUp')]
    public function test_an_event_outside_the_follow_up_window_is_not_followed_up(array $case): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('AFTER_EVENT', ['enabled_at' => now()->subDays($case['enabled_days_ago'] ?? 5), 'delay_days' => 1]);
        $organizerId = ($case['foreign'] ?? false) ? $this->createOrganizer($this->accountId, 'Other Organizer') : $this->organizerId;
        $eventId = $this->endedEvent('Winter Ball', endedHoursAgo: $case['ended_hours_ago'] ?? 30, organizerId: $organizerId);
        $this->holder($eventId, 'holder@example.com', optedIn: true, organizerId: $organizerId);
        DB::table('events')->where('id', $eventId)->update([
            'status' => $case['status'] ?? 'LIVE',
            'deleted_at' => ($case['deleted'] ?? false) ? now() : null,
        ]);
        if ($case['cancelled'] ?? false) {
            DB::table('event_occurrences')->where('event_id', $eventId)->update(['status' => 'CANCELLED']);
        }
        if ($case['handled'] ?? false) {
            $occurrenceId = DB::table('event_occurrences')->where('event_id', $eventId)->value('id');
            $this->insertCampaign(['status' => 'CANCELLED', 'automation_id' => $automationId, 'automation_run_key' => "occurrence:$occurrenceId"]);
        }

        $this->runAutomation($automationId);

        $this->assertCount(($case['handled'] ?? false) ? 1 : 0, $this->campaigns($automationId));
        Bus::assertNothingDispatched();
    }

    public static function eventsThatAreFollowedUp(): array
    {
        return [
            'ended exactly the delay ago' => [1, 24, 'LIVE'],
            'ended at the far edge of the seven day window' => [1, 24 * 8, 'LIVE'],
            'archived event' => [1, 30, 'ARCHIVED'],
            'no delay, ended an hour ago' => [0, 1, 'LIVE'],
            'three day delay' => [3, 24 * 3 + 1, 'LIVE'],
        ];
    }

    #[DataProvider('eventsThatAreFollowedUp')]
    public function test_an_event_inside_the_follow_up_window_is_followed_up(int $delayDays, int $endedHoursAgo, string $status): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('AFTER_EVENT', ['enabled_at' => now()->subDays(30), 'delay_days' => $delayDays]);
        $eventId = $this->endedEvent('Winter Ball', endedHoursAgo: $endedHoursAgo);
        $this->holder($eventId, 'holder@example.com', optedIn: true);
        DB::table('events')->where('id', $eventId)->update(['status' => $status]);

        $this->runAutomation($automationId);

        $this->assertSame(['holder@example.com'], $this->recipientEmails((int) $this->campaigns($automationId)->sole()->id));
    }

    public static function cancelledDates(): array
    {
        return [
            'cancelled date still to come' => [72],
            'cancelled date that ended inside the delay' => [-6],
            'cancelled date that ended before the automation was enabled' => [-24 * 20],
        ];
    }

    #[DataProvider('cancelledDates')]
    public function test_a_cancelled_date_never_decides_when_an_event_is_followed_up(int $cancelledStartsInHours): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('AFTER_EVENT', ['enabled_at' => now()->subDays(5), 'delay_days' => 1]);
        $eventId = $this->endedEvent('Winter Ball', endedHoursAgo: 30);
        $cancelledId = $this->insertOccurrence($eventId, now()->addHours($cancelledStartsInHours));
        DB::table('event_occurrences')->where('id', $cancelledId)->update(['status' => 'CANCELLED']);
        $this->holder($eventId, 'holder@example.com', optedIn: true);

        $this->runAutomation($automationId);

        $this->assertSame(['holder@example.com'], $this->recipientEmails((int) $this->campaigns($automationId)->sole()->id));
    }

    public function test_each_date_of_a_recurring_event_thanks_only_the_people_who_had_a_ticket_for_it(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('AFTER_EVENT', ['enabled_at' => now()->subDays(5), 'delay_days' => 1]);
        $eventId = $this->endedEvent('Winter Ball', endedHoursAgo: 30);
        $firstDateId = (int) DB::table('event_occurrences')->where('event_id', $eventId)->value('id');
        $secondDateId = $this->insertOccurrence($eventId, now()->addDays(3));
        $beforeEnabledId = $this->insertOccurrence($eventId, now()->subDays(6));
        $this->holder($eventId, 'first@example.com', optedIn: true, occurrenceId: $firstDateId);
        $this->holder($eventId, 'second@example.com', optedIn: true, occurrenceId: $secondDateId);
        $this->holder($eventId, 'early@example.com', optedIn: true, occurrenceId: $beforeEnabledId);

        $this->runAutomation($automationId);

        $first = $this->campaigns($automationId)->sole();
        $this->assertSame("occurrence:$firstDateId", $first->automation_run_key);
        $this->assertSame(['first@example.com'], $this->recipientEmails((int) $first->id));

        $this->travel(5)->days();
        $this->runAutomation($automationId);

        $campaigns = $this->campaigns($automationId);
        $this->assertSame(["occurrence:$firstDateId", "occurrence:$secondDateId"], $campaigns->pluck('automation_run_key')->all());
        $this->assertSame(['second@example.com'], $this->recipientEmails((int) $campaigns->last()->id));
    }

    public function test_a_ticket_without_a_date_counts_for_the_only_date_of_its_event_but_not_for_a_recurring_one(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('AFTER_EVENT', ['enabled_at' => now()->subDays(5), 'delay_days' => 1]);
        $singleDateEventId = $this->endedEvent('Winter Ball', endedHoursAgo: 30);
        $recurringEventId = $this->endedEvent('Weekly Quiz', endedHoursAgo: 30);
        $this->insertOccurrence($recurringEventId, now()->addDays(3));
        $this->holderWithoutDate($singleDateEventId, 'undated@example.com');
        $this->holderWithoutDate($recurringEventId, 'undated.recurring@example.com');

        $this->runAutomation($automationId);

        $campaigns = $this->campaigns($automationId)->keyBy('name');
        $this->assertSame(['undated@example.com'], $this->recipientEmails((int) $campaigns->first(static fn ($campaign) => str_starts_with($campaign->name, 'Winter Ball'))->id));
        $quiz = $campaigns->first(static fn ($campaign) => str_starts_with($campaign->name, 'Weekly Quiz'));
        $this->assertSame(['CANCELLED', 'AUTOMATION_SKIPPED'], [$quiz->status, $quiz->status_reason]);
    }

    public function test_a_follow_up_with_no_subscribed_ticket_holders_is_cancelled_once(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('AFTER_EVENT', ['enabled_at' => now()->subDays(5), 'delay_days' => 1]);
        $eventId = $this->endedEvent('Winter Ball', endedHoursAgo: 30);
        $this->holder($eventId, 'silent.holder@example.com', optedIn: false);

        $this->runAutomation($automationId);
        $this->runAutomation($automationId);

        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(['CANCELLED', 'AUTOMATION_SKIPPED'], [$campaign->status, $campaign->status_reason]);
        $this->assertNull($campaign->event_id);
        Bus::assertNothingDispatched();
    }

    public static function dailyTriggers(): array
    {
        return [
            'win back, eligible again after 180 days' => ['WIN_BACK', 'lapsed', 181, true],
            'win back, not again inside 180 days' => ['WIN_BACK', 'lapsed', 179, false],
            'welcome, never again' => ['WELCOME', 'first_timers', 1000, false],
        ];
    }

    #[DataProvider('dailyTriggers')]
    public function test_a_daily_automation_runs_once_a_day_and_does_not_email_the_same_contact_inside_its_repeat_window(
        string $trigger,
        string $defaultPreset,
        int $daysLater,
        bool $emailedAgain,
    ): void {
        Bus::fake([DispatchCampaignJob::class]);
        $this->travelTo(now()->setTime(10, 0));
        $automationId = $this->insertAutomation($trigger, ['subject' => 'Hello from {{ organizer_name }}', 'body' => '<p>{{ event_title }}{{ event_date }}Hello</p>']);
        $everyone = [['field' => 'orders_count', 'operator' => 'gte', 'value' => 0]];
        $this->eligibleContact($trigger, 'first@example.com');
        $today = now()->toDateString();

        $this->runAutomation($automationId);
        $this->eligibleContact($trigger, 'same.day@example.com');
        $this->travel(13)->hours();
        $this->runAutomation($automationId);

        $first = $this->campaigns($automationId)->sole();
        $this->assertSame(
            ["date:$today", $today, 'Hello from Test Organizer', '<p>Hello</p>', 'SENDING', null, null, $defaultPreset],
            [$first->automation_run_key, $first->name, $first->subject, $first->body, $first->status, $first->event_id, $first->segment_id, $first->preset_segment],
        );
        $this->assertSame(['first@example.com'], $this->recipientEmails((int) $first->id));

        $this->travel(2)->hours();
        $this->runAutomation($automationId);
        $this->assertCount(1, $this->campaigns($automationId));

        $this->travel(9)->hours();
        $this->runAutomation($automationId);

        $second = $this->campaigns($automationId)->last();
        $this->assertCount(2, $this->campaigns($automationId));
        $this->assertSame('date:'.now()->toDateString(), $second->automation_run_key);
        $this->assertSame(['same.day@example.com'], $this->recipientEmails((int) $second->id));

        $this->travel(1)->days();
        $this->runAutomation($automationId);
        $this->assertCount(2, $this->campaigns($automationId));
        $this->assertSame(now()->toDateTimeString(), DB::table('crm_automations')->where('id', $automationId)->value('last_run_at'));

        DB::table('crm_automations')->where('id', $automationId)->update(['segment_id' => $this->insertSavedSegment($everyone)]);
        $this->travelTo(Carbon::parse($first->created_at)->addDays($daysLater));
        $this->runAutomation($automationId);

        $emailedLater = $this->campaigns($automationId)->skip(2)->flatMap(fn (object $campaign) => $this->recipientEmails((int) $campaign->id))->all();
        $this->assertSame($emailedAgain, in_array('first@example.com', $emailedLater, true));
        $this->assertNotContains('quiet@example.com', $emailedLater);
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, count($this->campaigns($automationId)));
    }

    public static function earlierRecipientStatuses(): array
    {
        $cases = [];
        foreach (['WIN_BACK', 'WELCOME'] as $trigger) {
            foreach (['PENDING' => false, 'SENDING' => false, 'SENT' => false, 'FAILED' => true, 'SKIPPED' => true] as $status => $emailedAgain) {
                $cases["$trigger, earlier recipient $status"] = [$trigger, $status, 'SENDING', $emailedAgain];
            }
            foreach (['CANCELLED' => true, 'PAUSED' => true, 'PENDING_REVIEW' => false] as $campaignStatus => $emailedAgain) {
                $cases["$trigger, earlier recipient PENDING in a $campaignStatus campaign"] = [$trigger, 'PENDING', $campaignStatus, $emailedAgain];
            }
            $cases["$trigger, earlier recipient SKIPPED when its campaign was rejected or cancelled"] = [$trigger, 'SKIPPED', 'CANCELLED', false];
        }

        return $cases;
    }

    #[DataProvider('earlierRecipientStatuses')]
    public function test_a_daily_automation_emails_a_contact_again_only_when_the_earlier_email_never_went_out(string $trigger, string $earlierStatus, string $earlierCampaignStatus, bool $emailedAgain): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation($trigger);
        $earlierContactId = $this->eligibleContact($trigger, 'earlier@example.com');
        $this->eligibleContact($trigger, 'fresh@example.com');
        $yesterday = now()->subDay();
        $earlierCampaignId = $this->insertCampaign([
            'status' => $earlierCampaignStatus,
            'automation_id' => $automationId,
            'automation_run_key' => 'date:'.$yesterday->toDateString(),
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);
        $this->insertRecipient($earlierCampaignId, $earlierContactId, 'earlier@example.com', ['status' => $earlierStatus, 'created_at' => $yesterday, 'updated_at' => $yesterday]);

        $this->runAutomation($automationId);

        $campaigns = $this->campaigns($automationId);
        $this->assertCount(2, $campaigns);
        $this->assertSame(
            $emailedAgain ? ['earlier@example.com', 'fresh@example.com'] : ['fresh@example.com'],
            $this->recipientEmails((int) $campaigns->last()->id),
        );
    }

    public function test_a_daily_automation_creates_nothing_when_nobody_is_eligible(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $winBackId = $this->insertAutomation('WIN_BACK', ['last_failure_reason' => 'An earlier problem']);
        $welcomeId = $this->insertAutomation('WELCOME', ['last_failure_reason' => 'An earlier problem']);
        DB::table('crm_contacts')->where('email', 'quiet@example.com')->update(['last_order_at' => now()->subDays(10), 'first_seen_at' => now()->subDays(200)]);

        $this->runAutomation($winBackId);
        $this->runAutomation($welcomeId);

        $this->assertSame(0, DB::table('crm_campaigns')->whereIn('automation_id', [$winBackId, $welcomeId])->count());
        $this->assertSame(
            [now()->toDateTimeString(), now()->toDateTimeString()],
            DB::table('crm_automations')->whereIn('id', [$winBackId, $welcomeId])->pluck('last_run_at')->all(),
        );
        $this->assertSame(
            [null, null],
            DB::table('crm_automations')->whereIn('id', [$winBackId, $welcomeId])->pluck('last_failure_reason')->all(),
        );
        Bus::assertNothingDispatched();
    }

    public static function dailyAutomationTriggers(): array
    {
        return [
            'win back' => ['WIN_BACK'],
            'welcome' => ['WELCOME'],
        ];
    }

    #[DataProvider('dailyAutomationTriggers')]
    public function test_a_daily_automation_that_cannot_send_leaves_no_campaign_records_why_and_retries_the_same_day(string $trigger): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->travelTo(now()->setTime(10, 0));
        DB::table('accounts')->where('id', $this->accountId)->update(['account_verified_at' => null]);
        $automationId = $this->insertAutomation($trigger);
        $this->eligibleContact($trigger, 'eligible@example.com');

        $this->runAutomation($automationId);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $automation = DB::table('crm_automations')->where('id', $automationId)->first();
        $this->assertCount(0, $this->campaigns($automationId));
        $this->assertNull($automation->last_run_at);
        $this->assertNotNull(AutomationFailureDTO::decode($automation->last_failure_reason));
        Bus::assertNothingDispatched();

        DB::table('accounts')->where('id', $this->accountId)->update(['account_verified_at' => now()]);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);
        $this->runAutomation($automationId);

        $automation = DB::table('crm_automations')->where('id', $automationId)->first();
        $campaign = $this->campaigns($automationId)->sole();
        $this->assertSame(['SENDING', 'date:'.now()->toDateString()], [$campaign->status, $campaign->automation_run_key]);
        $this->assertSame(['eligible@example.com'], $this->recipientEmails((int) $campaign->id));
        $this->assertSame([now()->toDateTimeString(), null], [$automation->last_run_at, $automation->last_failure_reason]);
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
    }

    #[DataProvider('dailyAutomationTriggers')]
    public function test_a_daily_automation_goes_out_at_ten_in_the_morning_in_the_organizers_timezone(string $trigger): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        DB::table('organizers')->where('id', $this->organizerId)->update(['timezone' => 'America/Los_Angeles']);
        $firstDay = now('America/Los_Angeles')->addDay()->toImmutable();
        $secondDay = $firstDay->addDay();
        $this->travelTo($firstDay->setTime(9, 45));
        $automationId = $this->insertAutomation($trigger, ['last_run_at' => $firstDay->subDay()->setTime(10, 0)->utc()]);
        $this->eligibleContact($trigger, 'first@example.com');

        $this->runAutomation($automationId);
        $this->assertCount(0, $this->campaigns($automationId));

        $this->travel(15)->minutes();
        $this->runAutomation($automationId);
        $this->assertSame(['date:'.$firstDay->toDateString()], $this->campaigns($automationId)->pluck('automation_run_key')->all());

        $this->eligibleContact($trigger, 'evening@example.com');
        $this->travelTo($firstDay->setTime(17, 30));
        $this->runAutomation($automationId);
        $this->assertCount(1, $this->campaigns($automationId));

        $this->travelTo($secondDay->setTime(10, 0));
        $this->runAutomation($automationId);
        $this->assertSame(['date:'.$firstDay->toDateString(), 'date:'.$secondDay->toDateString()], $this->campaigns($automationId)->pluck('automation_run_key')->all());
        $this->assertSame(['evening@example.com'], $this->recipientEmails((int) $this->campaigns($automationId)->last()->id));
    }

    public function test_a_win_back_automation_only_emails_the_lapsed_buyers_of_its_ready_made_segment(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->eligibleContact('WIN_BACK', 'lapsed@example.com');
        $this->eligibleContact('WELCOME', 'recent@example.com');
        $withPresetId = $this->insertAutomation('WIN_BACK');

        $this->runAutomation($withPresetId);

        $campaign = $this->campaigns($withPresetId)->sole();
        $this->assertSame([null, 'lapsed'], [$campaign->segment_id, $campaign->preset_segment]);
        $this->assertSame(['lapsed@example.com'], $this->recipientEmails((int) $campaign->id));
    }

    public function test_the_welcome_automation_only_emails_contacts_first_seen_since_it_was_switched_on(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('WELCOME', ['enabled_at' => now()->subHours(2)]);
        $firstTimer = ['events_count' => 1, 'orders_count' => 1];
        $this->insertSubscribedContact('before@example.com', [...$firstTimer, 'first_seen_at' => now()->subHours(2)->subSecond()]);
        $this->insertSubscribedContact('at.the.switch@example.com', [...$firstTimer, 'first_seen_at' => now()->subHours(2)]);
        $this->insertSubscribedContact('after@example.com', [...$firstTimer, 'first_seen_at' => now()->subHour()]);
        $this->insertSubscribedContact('never.seen@example.com', $firstTimer);

        $this->runAutomation($automationId);

        $this->assertSame(
            ['after@example.com', 'at.the.switch@example.com'],
            $this->recipientEmails((int) $this->campaigns($automationId)->sole()->id),
        );
    }

    public function test_the_win_back_automation_emails_contacts_first_seen_before_it_was_switched_on(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('WIN_BACK', ['enabled_at' => now()->subHours(2)]);
        $this->eligibleContact('WIN_BACK', 'lapsed@example.com');

        $this->runAutomation($automationId);

        $this->assertSame(['lapsed@example.com'], $this->recipientEmails((int) $this->campaigns($automationId)->sole()->id));
    }

    public function test_a_daily_automation_respects_its_segment_or_preset(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->eligibleContact('WIN_BACK', 'lapsed@example.com');
        $segmentId = $this->insertSavedSegment([['field' => 'events_count', 'operator' => 'eq', 'value' => 1]]);
        $presetAutomationId = $this->insertAutomation('WIN_BACK', ['preset_segment' => 'top_fans']);
        $segmentAutomationId = $this->insertAutomation('WELCOME', ['segment_id' => $segmentId, 'preset_segment' => 'top_fans', 'enabled_at' => now()->subDays(250)]);

        $this->runAutomation($presetAutomationId);
        $this->runAutomation($segmentAutomationId);

        $byPreset = $this->campaigns($presetAutomationId)->sole();
        $bySegment = $this->campaigns($segmentAutomationId)->sole();
        $this->assertSame([null, 'top_fans'], [$byPreset->segment_id, $byPreset->preset_segment]);
        $this->assertSame([$segmentId, null], [(int) $bySegment->segment_id, $bySegment->preset_segment]);
        $this->assertSame(['ada@example.com'], $this->recipientEmails((int) $byPreset->id));
        $this->assertSame(['grace@example.com'], $this->recipientEmails((int) $bySegment->id));
    }

    public function test_being_emailed_by_one_automation_or_a_manual_campaign_does_not_exclude_a_contact_from_another(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $lapsedId = $this->eligibleContact('WIN_BACK', 'lapsed@example.com');
        $welcomeId = $this->insertAutomation('WELCOME');
        $winBackId = $this->insertAutomation('WIN_BACK');
        $this->insertRecipient($this->insertCampaign(['status' => 'SENT', 'automation_id' => $welcomeId, 'automation_run_key' => 'date:2026-01-01']), $lapsedId, 'lapsed@example.com', ['status' => 'SENT']);
        $this->insertRecipient($this->insertCampaign(['status' => 'SENT']), $lapsedId, 'lapsed@example.com', ['status' => 'SENT']);

        $this->runAutomation($winBackId);

        $this->assertSame(['lapsed@example.com'], $this->recipientEmails((int) $this->campaigns($winBackId)->sole()->id));
    }

    public function test_a_daily_run_is_named_after_its_automation_and_its_date(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('WIN_BACK', ['name' => 'We miss you']);
        $this->eligibleContact('WIN_BACK', 'lapsed@example.com');

        $this->runAutomation($automationId);

        $this->assertSame('We miss you · '.now()->toDateString(), $this->campaigns($automationId)->sole()->name);
    }

    public function test_a_refusal_in_the_same_cycle_is_still_reported_after_another_run_goes_out(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $this->liveEvent('First Night', now()->addDays(20));
        $this->liveEvent('Second Night', now()->addDays(21));
        $this->useTier(['max_recipients_per_message' => 5000]);
        $gate = Mockery::mock(CampaignSendGate::class, [
            app(AccountRepositoryInterface::class),
            app(CrmCampaignRepositoryInterface::class),
            app(CrmContactRepositoryInterface::class),
            app(MessagingEligibilityService::class),
            app('config'),
            app(CampaignSupportInbox::class),
        ])->makePartial();
        $gate->shouldReceive('evaluate')
            ->once()
            ->andThrow(new CampaignNotSendableException('No links.', CampaignNotSendableException::REASON_LINKS_NOT_ALLOWED));
        $gate->shouldReceive('evaluate')->passthru();
        $this->app->instance(CampaignSendGate::class, $gate);

        $this->runAutomation($automationId);

        $this->assertSame(['Second Night'], $this->campaigns($automationId)->pluck('name')->all());
        $this->assertSame('LINKS_NOT_ALLOWED', json_decode((string) DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'), true)['code'] ?? null);
    }

    public static function reasonsAndWhetherARunWithNobodyToEmailSettlesThem(): array
    {
        return [
            'a review that expired is only settled by an email going out' => ['REVIEW_EXPIRED', 'REVIEW_EXPIRED'],
            'too many recipients is only settled by an email going out' => ['TOO_MANY_RECIPIENTS', 'TOO_MANY_RECIPIENTS'],
            'an unconfirmed account is settled once a run gets as far as its audience' => ['ACCOUNT_NOT_VERIFIED', null],
        ];
    }

    #[DataProvider('reasonsAndWhetherARunWithNobodyToEmailSettlesThem')]
    public function test_a_run_with_nobody_to_email_only_clears_a_reason_it_has_shown_to_be_fixed(string $recorded, ?string $expected): void
    {
        $automationId = $this->insertAutomation('WIN_BACK', ['last_failure_reason' => (new AutomationFailureDTO($recorded))->forStorage()]);

        $this->runAutomation($automationId);

        $this->assertCount(0, $this->campaigns($automationId));
        $this->assertSame($expected, json_decode((string) DB::table('crm_automations')->where('id', $automationId)->value('last_failure_reason'), true)['code'] ?? null);
    }

    public function test_the_job_runs_enabled_automations_of_accounts_with_the_crm_flag_and_survives_a_failing_one(): void
    {
        Exceptions::fake();
        [, $flaggedOffAccountId] = $this->createAccountOwner();
        $this->setFeatureFlagOverride($flaggedOffAccountId, FeatureFlag::CRM, false);
        $flaggedOffOrganizerId = $this->createOrganizer($flaggedOffAccountId);
        $failingId = $this->insertAutomation('EVENT_PUBLISHED');
        $disabledId = $this->insertAutomation('AFTER_EVENT', ['is_enabled' => false, 'enabled_at' => null]);
        $healthyId = $this->insertAutomation('WIN_BACK');
        $lastId = $this->insertAutomation('WELCOME');
        $flaggedOffId = $this->insertAutomation('WELCOME', organizerId: $flaggedOffOrganizerId, accountId: $flaggedOffAccountId);
        $ran = [];
        $runner = Mockery::mock(AutomationRunner::class);
        $runner->shouldReceive('run')->andReturnUsing(static function (CrmAutomationDomainObject $automation) use (&$ran, $failingId): void {
            $ran[] = $automation->getId();

            if ($automation->getId() === $failingId) {
                throw new RuntimeException('The first automation broke.');
            }
        });
        $this->app->instance(AutomationRunner::class, $runner);

        RunAutomationsJob::dispatchSync();

        $ours = array_values(array_intersect($ran, [$failingId, $disabledId, $healthyId, $lastId, $flaggedOffId]));
        sort($ours);
        $this->assertSame([$failingId, $healthyId, $lastId], $ours);
        Exceptions::assertReported(static fn (RuntimeException $exception) => $exception->getMessage() === 'The first automation broke.');
    }

    public function test_more_automations_than_one_job_starts_are_continued_by_a_job_for_the_next_page(): void
    {
        Bus::fake([RunAutomationJob::class, RunAutomationsJob::class]);
        $automation = [
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'trigger' => 'WELCOME',
            'is_enabled' => true,
            'enabled_at' => now()->subHours(2),
            'subject' => 'Welcome',
            'body' => '<p>Welcome</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('crm_automations')->insert(array_fill(0, 501, $automation));
        $enabledIds = DB::table('crm_automations')->where('is_enabled', true)->orderBy('id')->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $started = static fn (): array => Bus::dispatched(RunAutomationJob::class)->map(static fn (RunAutomationJob $job) => $job->automationId)->all();

        app()->call([new RunAutomationsJob, 'handle']);

        $this->assertSame(array_slice($enabledIds, 0, 500), $started());
        Bus::assertDispatchedTimes(RunAutomationsJob::class, 1);
        Bus::assertDispatched(RunAutomationsJob::class, static fn (RunAutomationsJob $job) => $job->afterAutomationId === $enabledIds[499]);

        app()->call([new RunAutomationsJob($enabledIds[499]), 'handle']);

        $this->assertSame($enabledIds, $started());
        Bus::assertDispatchedTimes(RunAutomationsJob::class, 1);
    }

    #[DataProvider('triggersBoundedPerRun')]
    public function test_more_events_than_one_run_handles_are_finished_by_the_next_run(string $trigger): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation($trigger, ['enabled_at' => now()->subDays(5)]);

        if ($trigger === 'EVENT_PUBLISHED') {
            foreach (range(1, 24) as $number) {
                $this->liveEvent("Show $number", now()->addDays(20), withHiddenTicket: false);
            }
        } else {
            $eventId = $this->endedEvent('Winter Ball', endedHoursAgo: 30);
            foreach (range(1, 23) as $minute) {
                $this->insertOccurrence($eventId, now()->subHours(32)->subMinutes($minute));
            }
        }

        $this->runAutomation($automationId);
        $this->assertCount(20, $this->campaigns($automationId));

        $this->travel(15)->minutes();
        $this->runAutomation($automationId);
        $this->travel(15)->minutes();
        $this->runAutomation($automationId);

        $runKeys = $this->campaigns($automationId)->pluck('automation_run_key');
        $this->assertCount(24, $runKeys);
        $this->assertCount(24, $runKeys->unique());
    }

    public static function triggersBoundedPerRun(): array
    {
        return ['announcements' => ['EVENT_PUBLISHED'], 'follow-ups' => ['AFTER_EVENT']];
    }

    public function test_the_job_sends_real_follow_ups_end_to_end(): void
    {
        $automationId = $this->insertAutomation('AFTER_EVENT', ['enabled_at' => now()->subDays(5), 'delay_days' => 1, 'subject' => 'Thanks for coming to {{ event_title }}', 'body' => '<p>See you next time</p>']);
        $eventId = $this->endedEvent('Winter Ball', endedHoursAgo: 30);
        $this->holder($eventId, 'holder@example.com', optedIn: true);

        RunAutomationsJob::dispatchSync();
        RunAutomationsJob::dispatchSync();

        $this->assertSame('SENT', $this->campaigns($automationId)->sole()->status);
        $messages = $this->sentEmails();
        $this->assertSame(['holder@example.com'], $messages->keys()->all());
        $this->assertSame('Thanks for coming to Winter Ball', $messages['holder@example.com']->getSubject());
        $this->assertStringContainsString('See you next time', $messages['holder@example.com']->getHtmlBody());
        $this->assertStringNotContainsString('/event/', $messages['holder@example.com']->getHtmlBody());
        $this->assertTrue($messages['holder@example.com']->getHeaders()->has('List-Unsubscribe'));
    }

    public function test_the_job_does_nothing_for_an_account_whose_crm_flag_was_switched_off(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $automationId = $this->insertAutomation('EVENT_PUBLISHED');
        $this->liveEvent('Summer Party', now()->addDays(20));
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, false);

        RunAutomationsJob::dispatchSync();

        $this->assertCount(0, $this->campaigns($automationId));
        Bus::assertNothingDispatched();
    }

    private function insertAutomation(string $trigger, array $attributes = [], ?int $organizerId = null, ?int $accountId = null): int
    {
        return DB::table('crm_automations')->insertGetId([
            'account_id' => $accountId ?? $this->accountId,
            'organizer_id' => $organizerId ?? $this->organizerId,
            'trigger' => $trigger,
            'is_enabled' => true,
            'enabled_at' => now()->subHours(2),
            'subject' => 'Hello from {{ organizer_name }}',
            'body' => '<p>News about {{ event_title }}</p>',
            'delay_days' => $trigger === 'AFTER_EVENT' ? 1 : 0,
            'preset_segment' => CrmAutomationTrigger::from($trigger)->defaultPreset()?->value,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * @return array{subject: string, html: string}
     */
    private function renderedMail(int $campaignId, string $locale = 'en'): array
    {
        $previousLocale = App::getLocale();
        App::setLocale($locale);

        try {
            $mail = app(CampaignMailFactory::class)->make(
                app(CrmCampaignRepositoryInterface::class)->findById($campaignId),
                (int) DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->value('id'),
            );

            return ['subject' => $mail->envelope()->subject, 'html' => $mail->render()];
        } finally {
            App::setLocale($previousLocale);
        }
    }

    private function runAutomation(int $automationId): void
    {
        app(AutomationRunner::class)->run(app(CrmAutomationRepositoryInterface::class)->findById($automationId));
    }

    private function liveEvent(string $title, CarbonInterface $startsAt, array $attributes = [], ?int $organizerId = null, bool $withHiddenTicket = true): int
    {
        $eventId = $this->createEvent($this->accountId, $organizerId ?? $this->organizerId, $title);
        DB::table('events')->where('id', $eventId)->update([
            'status' => 'LIVE',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subMinutes(45),
            ...$attributes,
        ]);
        $this->insertOccurrence($eventId, $startsAt);
        [$productId] = $this->insertTicket($eventId);
        DB::table('products')->where('id', $productId)->update(['is_hidden_without_promo_code' => $withHiddenTicket]);

        return $eventId;
    }

    private function endedEvent(string $title, int $endedHoursAgo, ?int $organizerId = null): int
    {
        $eventId = $this->createEvent($this->accountId, $organizerId ?? $this->organizerId, $title);
        DB::table('events')->where('id', $eventId)->update(['status' => 'LIVE', 'created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)]);
        $this->insertOccurrence($eventId, now()->subHours($endedHoursAgo)->subHours(2));

        return $eventId;
    }

    private function holder(int $eventId, string $email, bool $optedIn, ?int $organizerId = null, ?int $occurrenceId = null): void
    {
        $placedAt = now()->subDays(40);
        $orderId = $this->insertOrder([
            'event_id' => $eventId,
            'email' => $email,
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
            'opted_into_marketing_at' => $optedIn ? $placedAt : null,
        ]);
        $this->insertAttendee($orderId, [
            'event_occurrence_id' => $occurrenceId ?? DB::table('event_occurrences')->where('event_id', $eventId)->where('status', 'ACTIVE')->orderBy('start_date')->value('id'),
        ]);
        app(ContactSyncService::class)->projectOrders($this->accountId, $organizerId ?? $this->organizerId, [$orderId]);
    }

    private function holderWithoutDate(int $eventId, string $email): void
    {
        $placedAt = now()->subDays(40);
        $orderId = $this->insertOrder([
            'event_id' => $eventId,
            'email' => $email,
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
            'opted_into_marketing_at' => $placedAt,
        ]);
        $this->insertAttendee($orderId, ['event_occurrence_id' => null]);
        app(ContactSyncService::class)->projectOrders($this->accountId, $this->organizerId, [$orderId]);
    }

    private function eligibleContact(string $trigger, string $email): int
    {
        return $this->insertSubscribedContact($email, $trigger === 'WIN_BACK'
            ? ['events_count' => 2, 'orders_count' => 2, 'first_seen_at' => now()->subDays(400), 'last_order_at' => now()->subDays(300)]
            : ['events_count' => 1, 'orders_count' => 1, 'first_seen_at' => now(), 'last_order_at' => now()]);
    }

    private function insertSpamCheck(int $eventId, string $status): int
    {
        return DB::table('event_spam_checks')->insertGetId([
            'event_id' => $eventId,
            'status' => $status,
            'content_hash' => hash('sha256', "event-$eventId"),
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function useTier(array $limits): int
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

        return $tierId;
    }

    private function insertSavedSegment(array $rules): int
    {
        return DB::table('crm_segments')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => 'Saved '.uniqid(),
            'definition' => json_encode(['match' => 'all', 'rules' => $rules]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, object>
     */
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

    private function spamChecksAre(bool $enabled): void
    {
        $this->app->instance(
            EventSpamCheckService::class,
            Mockery::mock(EventSpamCheckService::class)->shouldReceive('isEnabled')->andReturn($enabled)->getMock(),
        );
    }

    /**
     * @return Collection<string, Email>
     */
    private function sentEmails(): Collection
    {
        return app('mail.manager')->mailer()->getSymfonyTransport()->messages()
            ->mapWithKeys(static fn (SentMessage $message) => [$message->getOriginalMessage()->getTo()[0]->getAddress() => $message->getOriginalMessage()]);
    }
}
