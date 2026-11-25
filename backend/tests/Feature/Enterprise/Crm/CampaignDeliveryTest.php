<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\NoMailableRecipientsException;
use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\LaunchScheduledCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPausedJob;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPendingReviewJob;
use HiEvents\Enterprise\Crm\Jobs\RecordCampaignFeedbackJob;
use HiEvents\Enterprise\Crm\Jobs\SendCampaignEmailJob;
use HiEvents\Enterprise\Crm\Jobs\SendScheduledCampaignsJob;
use HiEvents\Enterprise\Crm\Mail\CampaignNotSentMail;
use HiEvents\Enterprise\Crm\Mail\CampaignPausedMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\CancelCampaignHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\CreateContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DeleteContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SetPrimaryContactEmailHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\UnsubscribeContactHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDeliveryService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDispatchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignFeedbackService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignHealthGuard;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignMailFactory;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignReportService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeTokenService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\EarlyAccessService;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class CampaignDeliveryTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    private int $adaId;

    private int $graceId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => false]);
        $this->setUpCrmOrganizer();
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);

        $this->adaId = $this->insertSubscribedContact('ada@example.com', ['first_name' => 'Ada', 'orders_count' => 3, 'events_count' => 3]);
        $this->graceId = $this->insertSubscribedContact('grace@example.com', ['first_name' => 'Grace', 'orders_count' => 1]);
    }

    public function test_only_subscribed_contacts_of_the_organizer_are_snapshotted_once_each(): void
    {
        Bus::fake([DispatchCampaignJob::class, SendCampaignEmailJob::class]);
        foreach (['NOT_SUBSCRIBED', 'UNSUBSCRIBED', 'BOUNCED', 'COMPLAINED'] as $status) {
            $this->insertContact(strtolower($status).'@example.com', ['email_marketing_status' => $status]);
        }
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $this->insertSubscribedContact('elsewhere@example.com', organizerId: $otherOrganizerId);
        DB::table('crm_contact_emails')->insert(['organizer_id' => $this->organizerId, 'contact_id' => $this->adaId, 'email' => 'ada.alias@example.com', 'created_at' => now(), 'updated_at' => now()]);
        $campaignId = $this->insertCampaign();

        $this->launch($campaignId);
        app(CampaignDispatchService::class)->dispatch($campaignId);
        app(CampaignDispatchService::class)->dispatch($campaignId);

        $this->assertSame(['ada@example.com', 'grace@example.com'], $this->recipientEmails($campaignId));
        $this->assertSame([$this->adaId, $this->graceId], DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->orderBy('email')->pluck('contact_id')->map(static fn ($id) => (int) $id)->all());
        $campaign = $this->campaign($campaignId);
        $this->assertSame(2, (int) $campaign->recipients_count);
        $this->assertNotNull($campaign->sent_at);
        $this->assertSame('SENDING', $campaign->status);
    }

    public static function audiences(): array
    {
        return [
            'everyone subscribed' => [[], ['ada@example.com', 'grace@example.com']],
            'preset' => [['preset_segment' => 'repeat_buyers'], ['ada@example.com']],
            'inline definition' => [['segment_definition' => json_encode(['match' => 'all', 'rules' => [['field' => 'orders_count', 'operator' => 'lte', 'value' => 1]]])], ['grace@example.com']],
            'saved segment' => [['segment_id' => 'saved'], ['ada@example.com']],
            'segment matching only unsubscribed contacts' => [['segment_definition' => json_encode(['match' => 'all', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 50]]])], []],
        ];
    }

    #[DataProvider('audiences')]
    public function test_the_campaign_audience_is_honoured(array $attributes, array $expected): void
    {
        Bus::fake([DispatchCampaignJob::class, SendCampaignEmailJob::class]);
        $this->insertContact('lapsed@example.com', ['email_marketing_status' => 'UNSUBSCRIBED', 'orders_count' => 99]);
        if (($attributes['segment_id'] ?? null) === 'saved') {
            $attributes['segment_id'] = $this->insertSavedSegment([['field' => 'orders_count', 'operator' => 'gte', 'value' => 2]]);
        }
        $campaignId = $this->insertCampaign($attributes);

        if ($expected === []) {
            try {
                $this->launch($campaignId);
                $this->fail('A campaign with nobody to send to was launched.');
            } catch (CampaignNotSendableException) {
            }

            $this->assertSame('DRAFT', $this->campaign($campaignId)->status);
            $this->assertSame([], $this->recipientEmails($campaignId));
            Bus::assertNothingDispatched();

            return;
        }

        $this->launch($campaignId);
        app(CampaignDispatchService::class)->dispatch($campaignId);

        $this->assertSame($expected, $this->recipientEmails($campaignId));
        Bus::assertDispatchedTimes(SendCampaignEmailJob::class, count($expected));
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
    }

    public function test_a_campaign_whose_saved_segment_was_deleted_is_refused_and_stays_a_draft(): void
    {
        Bus::fake([DispatchCampaignJob::class, SendCampaignEmailJob::class]);
        $campaignId = $this->insertCampaign(['segment_id' => 999999999]);

        try {
            $this->launch($campaignId);
            $this->fail('A campaign without a usable segment was launched.');
        } catch (CampaignNotSendableException) {
        }

        $campaign = $this->campaign($campaignId);
        $this->assertSame(['DRAFT', null], [$campaign->status, $campaign->sent_at]);
        $this->assertSame([], $this->recipientEmails($campaignId));
        Bus::assertNothingDispatched();
    }

    public function test_a_sending_campaign_without_recipients_is_finished_straight_away(): void
    {
        Bus::fake([SendCampaignEmailJob::class]);
        $campaignId = $this->insertCampaign(['status' => 'SENDING']);

        app(CampaignDispatchService::class)->dispatch($campaignId);

        $this->assertSame('SENT', $this->campaign($campaignId)->status);
        Bus::assertNothingDispatched();
    }

    #[DataProvider('idleStatuses')]
    public function test_dispatching_a_campaign_that_is_not_sending_does_nothing(string $status): void
    {
        Bus::fake([SendCampaignEmailJob::class]);
        $campaignId = $this->insertCampaign(['status' => $status]);

        (new DispatchCampaignJob($campaignId))->handle(app(CampaignDispatchService::class));

        $this->assertSame($status, $this->campaign($campaignId)->status);
        $this->assertSame([], $this->recipientEmails($campaignId));
        Bus::assertNothingDispatched();
    }

    public static function idleStatuses(): array
    {
        return [
            'draft' => ['DRAFT'],
            'scheduled' => ['SCHEDULED'],
            'pending review' => ['PENDING_REVIEW'],
            'paused' => ['PAUSED'],
            'cancelled' => ['CANCELLED'],
            'sent' => ['SENT'],
            'rejected' => ['REJECTED'],
        ];
    }

    public function test_a_resumed_dispatch_queues_only_pending_recipients_and_never_adds_new_ones(): void
    {
        Bus::fake([SendCampaignEmailJob::class]);
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinutes(5), 'recipients_count' => 2]);
        $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['status' => 'SENT']);
        $pendingId = $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com');
        $this->insertSubscribedContact('late@example.com');

        app(CampaignDispatchService::class)->dispatch($campaignId);

        $this->assertSame(['ada@example.com', 'grace@example.com'], $this->recipientEmails($campaignId));
        Bus::assertDispatchedTimes(SendCampaignEmailJob::class, 1);
        Bus::assertDispatched(SendCampaignEmailJob::class, static fn (SendCampaignEmailJob $job) => $job->recipientId === $pendingId);
    }

    public function test_a_campaign_is_delivered_to_every_recipient_and_then_marked_sent(): void
    {
        config(['app.url' => 'https://tickets.example.com', 'app.api_url' => 'https://tickets.example.com', 'app.frontend_url' => 'https://tickets.example.com']);
        DB::table('crm_contacts')->where('id', $this->graceId)->update(['locale' => 'de']);
        $campaignId = $this->insertCampaign([
            'name' => 'Spring launch',
            'subject' => 'Doors open soon',
            'body' => '<p>See you at the show</p><script>alert("x")</script>',
        ]);

        $this->launch($campaignId);

        $campaign = $this->campaign($campaignId);
        $this->assertSame(['SENT', 2], [$campaign->status, (int) $campaign->recipients_count]);
        $recipients = DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->orderBy('email')->get();
        $this->assertSame(['SENT', 'SENT'], $recipients->pluck('status')->all());
        $this->assertCount(2, $recipients->pluck('provider_message_id')->filter()->unique());
        $this->assertNotNull($recipients[0]->sent_at);

        $messages = $this->sentEmails();
        $this->assertSame(['ada@example.com', 'grace@example.com'], $messages->keys()->sort()->values()->all());

        $tokens = app(CampaignUnsubscribeTokenService::class);
        foreach ($recipients as $recipient) {
            $email = $messages[$recipient->email];
            $this->assertCount(1, $email->getTo());
            $this->assertSame('Doors open soon', $email->getSubject());
            $this->assertSame('<https://tickets.example.com/api/public/crm/unsubscribe/'.$tokens->tokenFor((int) $recipient->id).'>', $email->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
            $this->assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
            $this->assertStringContainsString(e($tokens->pageUrl((int) $recipient->id)), $email->getHtmlBody());
            $this->assertStringContainsString('See you at the show', $email->getHtmlBody());
            $this->assertStringContainsString('Test Organizer', $email->getHtmlBody());
            $this->assertStringNotContainsString('<script', $email->getHtmlBody());
            $this->assertStringNotContainsString('alert(', $email->getHtmlBody());
            $this->assertSame(
                DB::table('organizers')->where('id', $this->organizerId)->value('email'),
                $email->getReplyTo()[0]->getAddress(),
            );
        }

        $activity = DB::table('crm_contact_activities')->where('contact_id', $this->adaId)->where('type', 'CAMPAIGN_SENT')->sole();
        $this->assertSame(['Spring launch', 'ada@example.com', (int) $recipients[0]->id], [$activity->label, $activity->email, (int) $activity->source_id]);
    }

    public function test_each_recipient_is_greeted_by_their_own_name_or_the_fallback(): void
    {
        DB::table('crm_contacts')->where('id', $this->adaId)->update(['first_name' => '<b>Ada</b>', 'last_name' => 'Lovelace']);
        DB::table('crm_contacts')->where('id', $this->graceId)->update(['first_name' => null]);
        $campaignId = $this->insertCampaign([
            'subject' => 'Hi {{ first_name | default: "there" }}, doors open soon',
            'body' => '<p>Dear {{ first_name | default: &quot;friend&quot; }} {{ last_name }},</p>',
        ]);

        $this->launch($campaignId);

        $messages = $this->sentEmails();
        $this->assertSame('Hi <b>Ada</b>, doors open soon', $messages['ada@example.com']->getSubject());
        $this->assertStringContainsString('Dear &lt;b&gt;Ada&lt;/b&gt; Lovelace,', $messages['ada@example.com']->getHtmlBody());
        $this->assertSame('Hi there, doors open soon', $messages['grace@example.com']->getSubject());
        $this->assertStringContainsString('Dear friend ,', $messages['grace@example.com']->getHtmlBody());
    }

    public static function changesBetweenSnapshotAndSend(): array
    {
        return [
            'unsubscribed' => ['unsubscribed', 'UNSUBSCRIBED'],
            'contact deleted' => ['deleted', 'CONTACT_DELETED'],
            'primary email changed' => ['primary_changed', 'EMAIL_CHANGED'],
            'bounced on an earlier campaign' => ['bounced', 'BOUNCED'],
        ];
    }

    #[DataProvider('changesBetweenSnapshotAndSend')]
    public function test_a_recipient_who_stopped_being_mailable_after_the_snapshot_is_skipped_with_the_reason(string $change, string $expectedReason): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $campaignId = $this->insertCampaign();
        $this->launch($campaignId);
        $recipients = DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->pluck('id', 'email');

        switch ($change) {
            case 'unsubscribed':
                app(UnsubscribeContactHandler::class)->handle($this->organizerId, $this->adaId, $this->owner->id);
                break;
            case 'deleted':
                app(DeleteContactHandler::class)->handle($this->organizerId, $this->adaId);
                break;
            case 'primary_changed':
                $aliasId = DB::table('crm_contact_emails')->insertGetId(['organizer_id' => $this->organizerId, 'contact_id' => $this->adaId, 'email' => 'ada.new@example.com', 'created_at' => now(), 'updated_at' => now()]);
                $this->insertConsent($this->adaId, 'ada.new@example.com', 'OPT_IN', now()->subDay());
                app(SetPrimaryContactEmailHandler::class)->handle($this->organizerId, $this->adaId, $aliasId);
                $this->assertSame('SUBSCRIBED', DB::table('crm_contacts')->where('id', $this->adaId)->value('email_marketing_status'));
                break;
            case 'bounced':
                $earlierId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subDay()]);
                $this->insertRecipient($earlierId, $this->adaId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'earlier-ada']);
                $this->insertDelivery('earlier-ada', ['bounced_at' => now(), 'bounce_type' => 'PERMANENT']);
                app(CampaignFeedbackService::class)->recordBouncesAndComplaints();
                break;
        }

        $delivery = app(CampaignDeliveryService::class);
        $delivery->deliver((int) $recipients['ada@example.com']);
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        $delivery->deliver((int) $recipients['grace@example.com']);

        $this->assertSame(
            ['SKIPPED', 'SENT'],
            array_map(
                static fn (string $email) => DB::table('crm_campaign_recipients')->where('id', $recipients[$email])->value('status'),
                ['ada@example.com', 'grace@example.com'],
            ),
        );
        $this->assertSame($expectedReason, DB::table('crm_campaign_recipients')->where('id', $recipients['ada@example.com'])->value('failure_reason'));
        $this->assertSame(['grace@example.com'], $this->sentEmails()->keys()->all());
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
        $this->assertSame(0, DB::table('crm_contact_activities')->where('type', 'CAMPAIGN_SENT')->where('email', 'ada@example.com')->count());
    }

    public static function stoppedStatuses(): array
    {
        return [
            'paused' => ['PAUSED', 'SKIPPED'],
            'cancelled' => ['CANCELLED', 'SKIPPED'],
            'back in draft' => ['DRAFT', 'PENDING'],
        ];
    }

    #[DataProvider('stoppedStatuses')]
    public function test_a_stopped_campaign_sends_nothing_further(string $status, string $recipientStatus): void
    {
        $campaignId = $this->insertCampaign(['status' => $status, 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');

        (new SendCampaignEmailJob($recipientId))->handle(app(CampaignDeliveryService::class));

        $this->assertCount(0, $this->sentEmails());
        $this->assertSame($recipientStatus, DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('status'));
        $this->assertSame($status, $this->campaign($campaignId)->status);
    }

    public function test_cancelling_a_sending_campaign_skips_its_pending_recipients_and_keeps_the_rest(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute(), 'launched_at' => now()->subMinute()]);
        $pendingId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $sentId = $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com', ['status' => 'SENT']);

        app(CancelCampaignHandler::class)->handle($this->organizerId, $campaignId, true);
        app(CampaignDeliveryService::class)->deliver($pendingId);

        $this->assertSame('CANCELLED', $this->campaign($campaignId)->status);
        $this->assertSame(['SKIPPED', 'SENT'], [$this->recipientStatus($pendingId), $this->recipientStatus($sentId)]);
        $this->assertNotNull($this->campaign($campaignId)->launched_at);
        $this->assertCount(0, $this->sentEmails());
    }

    public function test_withdrawing_a_campaign_from_review_removes_its_recipients_and_its_launch(): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        config(['app.saas_mode_enabled' => true]);
        $campaignId = $this->insertCampaign();
        $this->assertSame('PENDING_REVIEW', $this->launch($campaignId)->value);
        $this->assertSame(2, (int) $this->campaign($campaignId)->recipients_count);
        $this->assertNotNull($this->campaign($campaignId)->launched_at);

        app(CancelCampaignHandler::class)->handle($this->organizerId, $campaignId, true);

        $campaign = $this->campaign($campaignId);
        $this->assertSame(['DRAFT', 0, null], [$campaign->status, (int) $campaign->recipients_count, $campaign->launched_at]);
        $this->assertSame([], $this->recipientEmails($campaignId));
    }

    public function test_a_skip_or_failure_never_overwrites_a_recipient_that_already_moved_on(): void
    {
        $repository = app(CrmCampaignRepositoryInterface::class);
        $campaignId = $this->insertCampaign(['status' => 'SENDING']);
        $ids = [];
        foreach (['PENDING', 'SENDING', 'SENT', 'FAILED', 'SKIPPED'] as $status) {
            $ids[$status] = $this->insertRecipient($campaignId, null, strtolower($status).'@example.com', ['status' => $status]);
        }

        $skipped = array_map(static fn (int $id) => $repository->markRecipientSkipped($id, CrmRecipientFailureReason::CAMPAIGN_STOPPED), $ids);

        $this->assertSame(['PENDING' => true, 'SENDING' => false, 'SENT' => false, 'FAILED' => false, 'SKIPPED' => false], $skipped);
        $this->assertSame(['SKIPPED', 'SENDING', 'SENT', 'FAILED', 'SKIPPED'], array_values(array_map(fn (int $id) => $this->recipientStatus($id), $ids)));

        DB::table('crm_campaign_recipients')->where('id', $ids['PENDING'])->update(['status' => 'PENDING']);
        $failed = array_map(static fn (int $id) => $repository->markRecipientFailed($id, CrmRecipientFailureReason::SEND_ERROR), $ids);

        $this->assertSame(['PENDING' => true, 'SENDING' => true, 'SENT' => false, 'FAILED' => false, 'SKIPPED' => false], $failed);
        $this->assertSame(['FAILED', 'FAILED', 'SENT', 'FAILED', 'SKIPPED'], array_values(array_map(fn (int $id) => $this->recipientStatus($id), $ids)));
    }

    public function test_a_launch_that_finds_nobody_left_to_snapshot_changes_nothing(): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        $campaignId = $this->insertCampaign();
        $repository = Mockery::mock(app(CrmCampaignRepositoryInterface::class));
        $repository->shouldReceive('snapshotRecipients')->once()->andReturn(0);
        $this->app->instance(CrmCampaignRepositoryInterface::class, $repository);

        try {
            $this->launch($campaignId);
            $this->fail('The campaign was launched.');
        } catch (NoMailableRecipientsException) {
        }

        $campaign = $this->campaign($campaignId);
        $this->assertSame(['DRAFT', null], [$campaign->status, $campaign->launched_at]);
        Bus::assertNothingDispatched();
    }

    public function test_a_campaign_whose_organizer_is_deleted_mid_send_is_cancelled_and_its_remaining_recipients_skipped(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $firstId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $secondId = $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com');
        DB::table('organizers')->where('id', $this->organizerId)->update(['deleted_at' => now()]);

        app(CampaignDeliveryService::class)->deliver($firstId);
        app(CampaignDeliveryService::class)->deliver($secondId);

        $campaign = $this->campaign($campaignId);
        $this->assertSame(['CANCELLED', CampaignDispatchService::REASON_ORGANIZER_DELETED], [$campaign->status, $campaign->status_reason]);
        $this->assertSame(
            [['SKIPPED', 'CAMPAIGN_STOPPED'], ['SKIPPED', 'CAMPAIGN_STOPPED']],
            array_map(fn (int $id) => [$this->recipientStatus($id), $this->failureReason($id)], [$firstId, $secondId]),
        );
        $this->assertCount(0, $this->sentEmails());
    }

    public function test_the_button_is_labelled_in_the_recipients_language(): void
    {
        DB::table('crm_contacts')->where('id', $this->adaId)->update(['locale' => 'de']);
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute(), 'event_id' => $this->eventId, 'promo_code_id' => 999999]);
        $adaRecipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $graceRecipientId = $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com');

        app(CampaignDeliveryService::class)->deliver($adaRecipientId);
        app(CampaignDeliveryService::class)->deliver($graceRecipientId);

        $this->assertStringContainsString('Tickets erhalten', $this->sentEmails()['ada@example.com']->getHtmlBody());
        $this->assertStringNotContainsString('Get tickets', $this->sentEmails()['ada@example.com']->getHtmlBody());
        $this->assertStringContainsString('Get tickets', $this->sentEmails()['grace@example.com']->getHtmlBody());
        $this->assertSame('en', app()->getLocale());
    }

    public static function pauseNotifications(): array
    {
        return [
            'saas' => [true, 1],
            'self-hosted' => [false, 0],
        ];
    }

    #[DataProvider('pauseNotifications')]
    public function test_a_stopped_campaign_tells_its_creator_and_in_saas_mode_platform_support(bool $isSaas, int $supportMails): void
    {
        Mail::fake();
        config(['app.saas_mode_enabled' => $isSaas, 'app.platform_support_email' => 'support@hi.events.test']);
        $campaignId = $this->insertCampaign(['status' => 'PAUSED', 'status_reason' => 'COMPLAINT_RATE']);

        app()->call([new NotifyCampaignPausedJob($campaignId), 'handle']);

        Mail::assertQueued(CampaignNotSentMail::class, 1);
        Mail::assertQueued(CampaignPausedMail::class, $supportMails);
        if ($supportMails > 0) {
            Mail::assertQueued(CampaignPausedMail::class, static fn (CampaignPausedMail $mail) => $mail->hasTo('support@hi.events.test'));
        }
    }

    public function test_a_recipient_is_never_mailed_twice(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com');

        app(CampaignDeliveryService::class)->deliver($recipientId);
        app(CampaignDeliveryService::class)->deliver($recipientId);
        app(CampaignDeliveryService::class)->deliver(999999999);

        $this->assertCount(1, $this->sentEmails());
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        $this->assertSame(1, DB::table('crm_contact_activities')->where('type', 'CAMPAIGN_SENT')->count());
    }

    public function test_a_failed_send_is_recorded_and_still_lets_the_campaign_finish(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $failedId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $sentId = $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com', ['status' => 'SENT']);

        (new SendCampaignEmailJob($sentId))->failed(null);
        (new SendCampaignEmailJob($failedId))->failed(null);

        $this->assertSame(['FAILED', 'SENT'], DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->orderBy('email')->pluck('status')->all());
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_a_send_the_provider_refused_returns_the_recipient_to_pending_so_a_retry_sends_it_once(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com', ['status' => 'SENT']);
        $statusWhenSent = null;
        app('mail.manager')->extend('refusing', static function () use ($recipientId, &$statusWhenSent) {
            return new class($recipientId, $statusWhenSent) extends AbstractTransport
            {
                public function __construct(private readonly int $recipientId, private mixed &$statusWhenSent)
                {
                    parent::__construct();
                }

                protected function doSend(SentMessage $message): void
                {
                    $this->statusWhenSent = DB::table('crm_campaign_recipients')->where('id', $this->recipientId)->value('status');

                    throw new TransportException('Connection could not be established with host "smtp.example.test:587": stream_socket_client(): Unable to connect to smtp.example.test:587 (Connection refused)');
                }

                public function __toString(): string
                {
                    return 'refusing';
                }
            };
        });
        config(['mail.mailers.refusing' => ['transport' => 'refusing'], 'mail.campaigns.mailer' => 'refusing']);

        try {
            app(CampaignDeliveryService::class)->deliver($recipientId);
            $this->fail('The refused send was swallowed.');
        } catch (TransportException) {
        }

        $this->assertSame('SENDING', $statusWhenSent);
        $this->assertSame('PENDING', DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('status'));
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        $this->assertSame(0, DB::table('crm_contact_activities')->where('type', 'CAMPAIGN_SENT')->count());

        config(['mail.campaigns.mailer' => null]);
        app(CampaignDeliveryService::class)->deliver($recipientId);
        app(CampaignDeliveryService::class)->deliver($recipientId);

        $this->assertSame('SENT', DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('status'));
        $this->assertSame(['ada@example.com'], $this->sentEmails()->keys()->all());
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_an_address_the_provider_has_marked_inactive_is_recorded_as_bounced_and_not_retried(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com', ['status' => 'SENT']);
        $this->useCampaignTransportThatThrows(static fn () => new HttpTransportException(
            'Unable to send an email: inactive recipient (code 406).',
            (new MockHttpClient(new MockResponse('{"ErrorCode":406,"Message":"inactive recipient"}', ['http_code' => 422])))->request('POST', 'https://api.postmarkapp.com/email'),
        ));

        app(CampaignDeliveryService::class)->deliver($recipientId);

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame('FAILED', $recipient->status);
        $this->assertSame('PROVIDER_SUPPRESSED', $recipient->failure_reason);
        $this->assertSame('BOUNCED', DB::table('crm_contacts')->where('id', $this->adaId)->value('email_marketing_status'));
        $this->assertSame(1, DB::table('crm_consent_events')->where(['contact_id' => $this->adaId, 'action' => 'BOUNCED', 'source_type' => 'CAMPAIGN', 'source_id' => $recipientId])->count());
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public static function refusals(): array
    {
        return [
            'an api rejection of the message' => [
                static fn () => new HttpTransportException(
                    'Unable to send an email: invalid sender (code 400).',
                    (new MockHttpClient(new MockResponse('{"ErrorCode":400,"Message":"invalid sender"}', ['http_code' => 422])))->request('POST', 'https://api.postmarkapp.com/email'),
                ),
                'PROVIDER_REJECTED',
                'SUBSCRIBED',
            ],
            'an smtp server that refuses the sender' => [
                static fn () => self::smtpRefusal('550 5.7.1 Sender address rejected: not owned by user', []),
                'PROVIDER_REJECTED',
                'SUBSCRIBED',
            ],
            'an smtp server that refuses the message content' => [
                static fn () => self::smtpRefusal('554 5.7.1 Message rejected as spam', [250, 250, 354]),
                'PROVIDER_REJECTED',
                'SUBSCRIBED',
            ],
            'an smtp server that says the mailbox does not exist' => [
                static fn () => self::smtpRefusal('550 5.1.1 The email account that you tried to reach does not exist', [250]),
                'PROVIDER_SUPPRESSED',
                'BOUNCED',
            ],
        ];
    }

    #[DataProvider('refusals')]
    public function test_a_send_the_provider_refused_for_good_fails_at_once_and_only_a_bad_address_is_recorded_as_bounced(callable $makeException, string $expectedReason, string $expectedConsent): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com', ['status' => 'SENT']);
        $this->useCampaignTransportThatThrows($makeException);

        app(CampaignDeliveryService::class)->deliver($recipientId);

        $this->assertSame(['FAILED', $expectedReason], [$this->recipientStatus($recipientId), $this->failureReason($recipientId)]);
        $this->assertSame($expectedConsent, DB::table('crm_contacts')->where('id', $this->adaId)->value('email_marketing_status'));
        $this->assertSame(
            $expectedConsent === 'BOUNCED' ? 1 : 0,
            DB::table('crm_consent_events')->where(['contact_id' => $this->adaId, 'action' => 'BOUNCED', 'source_type' => 'CAMPAIGN', 'source_id' => $recipientId])->count(),
        );
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public static function errorsAfterTheMessageLeft(): array
    {
        return [
            'the api answered with a server error' => [static fn () => new HttpTransportException(
                'Unable to send an email: Internal Server Error (code 0).',
                (new MockHttpClient(new MockResponse('{}', ['http_code' => 500])))->request('POST', 'https://api.postmarkapp.com/email'),
            )],
            'the smtp connection dropped after the message was handed over' => [static function () {
                $exception = new TransportException('Connection to "smtp.example.test:587" has been closed unexpectedly.');
                $exception->appendDebug(self::smtpTranscript([250, 250, 354]));

                return $exception;
            }],
        ];
    }

    #[DataProvider('errorsAfterTheMessageLeft')]
    public function test_an_error_after_the_message_left_is_never_retried_in_case_it_was_delivered(callable $makeException): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $this->useCampaignTransportThatThrows($makeException);
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldNotReceive('release');

        (new SendCampaignEmailJob($recipientId))->setJob($queueJob)->handle(app(CampaignDeliveryService::class));

        $this->assertSame(['FAILED', 'TIMEOUT'], [$this->recipientStatus($recipientId), $this->failureReason($recipientId)]);
        $this->assertSame('SUBSCRIBED', DB::table('crm_contacts')->where('id', $this->adaId)->value('email_marketing_status'));
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public static function temporaryRefusals(): array
    {
        return [
            'the api is rate limiting' => [static fn () => new HttpTransportException(
                'Unable to send an email: Too many requests (code 0).',
                (new MockHttpClient(new MockResponse('{}', ['http_code' => 429])))->request('POST', 'https://api.postmarkapp.com/email'),
            )],
            'the smtp server asks to try again later' => [static fn () => self::smtpRefusal('451 4.7.1 Greylisted, try again later', [250])],
            'the smtp login is refused before any message is named' => [static function () {
                $exception = new TransportException('Failed to authenticate on SMTP server with username "mailer".', 535);
                $exception->appendDebug("[2026-11-18T10:00:00.000000+00:00] < 220 smtp.example.test ESMTP\r\n[2026-11-18T10:00:00.000000+00:00] > EHLO app\r\n[2026-11-18T10:00:00.000000+00:00] < 250 OK\r\n");

                return $exception;
            }],
        ];
    }

    #[DataProvider('temporaryRefusals')]
    public function test_a_send_the_provider_turned_away_for_now_goes_back_to_pending_for_the_retry(callable $makeException): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $this->useCampaignTransportThatThrows($makeException);
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('retryUntil')->andReturn(now()->addHour()->getTimestamp());
        $queueJob->shouldReceive('release')->once()->with(30);

        (new SendCampaignEmailJob($recipientId))->setJob($queueJob)->handle(app(CampaignDeliveryService::class));

        $this->assertSame(['PENDING', null], [$this->recipientStatus($recipientId), $this->failureReason($recipientId)]);
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
    }

    /**
     * @param  int[]  $acceptedReplies
     */
    private static function smtpRefusal(string $reply, array $acceptedReplies): UnexpectedResponseException
    {
        $exception = new UnexpectedResponseException('Expected response code "250" but got code "'.substr($reply, 0, 3).'", with message "'.$reply.'".', (int) substr($reply, 0, 3));
        $exception->appendDebug(self::smtpTranscript($acceptedReplies).'[2026-11-18T10:00:00.000000+00:00] < '.$reply."\r\n");

        return $exception;
    }

    /**
     * @param  int[]  $replies
     */
    private static function smtpTranscript(array $replies): string
    {
        $commands = ['MAIL FROM:<campaigns@example.test>', 'RCPT TO:<ada@example.com>', 'DATA', '.'];
        $transcript = '';

        foreach ($replies as $index => $reply) {
            $transcript .= '[2026-11-18T10:00:00.000000+00:00] > '.$commands[$index]."\n".'[2026-11-18T10:00:00.000000+00:00] < '.$reply." OK\r\n";
        }

        return $transcript.(isset($commands[count($replies)]) ? '[2026-11-18T10:00:00.000000+00:00] > '.$commands[count($replies)]."\n" : '');
    }

    public function test_a_send_that_timed_out_is_failed_rather_than_retried_because_the_provider_may_have_accepted_it(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $this->useCampaignTransportThatThrows(static fn () => new TransportException(
            'Could not reach the remote Postmark server.',
            0,
            new TransportException('Idle timeout reached for "https://api.postmarkapp.com/email".'),
        ));

        app(CampaignDeliveryService::class)->deliver($recipientId);

        $this->assertSame('FAILED', $this->recipientStatus($recipientId));
        $this->assertSame('TIMEOUT', DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('failure_reason'));
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_a_provider_outage_leaves_the_recipient_pending_and_the_campaign_sending_until_the_job_gives_up(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $this->useCampaignTransportThatThrows(static fn () => new TransportException('Connection could not be established with host "smtp.example.test:587": stream_socket_client(): Unable to connect to smtp.example.test:587 (Connection refused)'));
        $withinWindow = Mockery::mock(QueueJob::class);
        $withinWindow->shouldReceive('attempts')->andReturn(3);
        $withinWindow->shouldReceive('retryUntil')->andReturn(now()->addHour()->getTimestamp());
        $withinWindow->shouldReceive('release')->once();

        (new SendCampaignEmailJob($recipientId))->setJob($withinWindow)->handle(app(CampaignDeliveryService::class));

        $this->assertSame(['PENDING', null], [$this->recipientStatus($recipientId), DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('failure_reason')]);
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);

        $windowOver = Mockery::mock(QueueJob::class);
        $windowOver->shouldReceive('attempts')->andReturn(14);
        $windowOver->shouldReceive('retryUntil')->andReturn(now()->addMinute()->getTimestamp());
        $windowOver->shouldNotReceive('release');

        (new SendCampaignEmailJob($recipientId))->setJob($windowOver)->handle(app(CampaignDeliveryService::class));

        $this->assertSame(['FAILED', 'PROVIDER_UNAVAILABLE'], [$this->recipientStatus($recipientId), DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('failure_reason')]);
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_a_recipient_is_sent_once_the_provider_recovers_within_the_retry_window(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $this->useCampaignTransportThatThrows(static fn () => new TransportException('Connection could not be established with host "smtp.example.test:587": stream_socket_client(): Unable to connect to smtp.example.test:587 (Connection refused)'));
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('attempts')->andReturn(1, 2);
        $queueJob->shouldReceive('retryUntil')->andReturn(now()->addHour()->getTimestamp());
        $queueJob->shouldReceive('release')->once()->with(30);
        $job = (new SendCampaignEmailJob($recipientId))->setJob($queueJob);

        $job->handle(app(CampaignDeliveryService::class));
        config(['mail.campaigns.mailer' => null]);
        $job->handle(app(CampaignDeliveryService::class));

        $this->assertSame('SENT', $this->recipientStatus($recipientId));
        $this->assertSame(['ada@example.com'], $this->sentEmails()->keys()->all());
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_a_mail_that_cannot_be_built_leaves_the_recipient_pending_for_the_retry(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $factory = Mockery::mock(CampaignMailFactory::class);
        $factory->shouldReceive('make')->once()->andThrow(new RuntimeException('The database went away.'));
        $this->app->instance(CampaignMailFactory::class, $factory);

        try {
            app(CampaignDeliveryService::class)->deliver($recipientId);
            $this->fail('The failure was swallowed.');
        } catch (RuntimeException) {
        }

        $this->assertSame('PENDING', $this->recipientStatus($recipientId));
    }

    public function test_a_bounce_recorded_from_both_the_rejection_and_the_feedback_job_is_stored_once(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $this->useCampaignTransportThatThrows(static fn () => new HttpTransportException(
            'Unable to send an email: inactive recipient (code 406).',
            (new MockHttpClient(new MockResponse('{"ErrorCode":406}', ['http_code' => 422])))->request('POST', 'https://api.postmarkapp.com/email'),
        ));

        app(CampaignDeliveryService::class)->deliver($recipientId);
        app(CampaignDeliveryService::class)->deliver($recipientId);

        $this->assertSame(1, DB::table('crm_consent_events')->where(['contact_id' => $this->adaId, 'action' => 'BOUNCED'])->count());
    }

    private function useCampaignTransportThatThrows(callable $makeException): void
    {
        app('mail.manager')->extend('throwing', static fn () => new class($makeException) extends AbstractTransport
        {
            private $makeException;

            public function __construct(callable $makeException)
            {
                parent::__construct();
                $this->makeException = $makeException;
            }

            protected function doSend(SentMessage $message): void
            {
                throw ($this->makeException)();
            }

            public function __toString(): string
            {
                return 'throwing';
            }
        });
        config(['mail.mailers.throwing' => ['transport' => 'throwing'], 'mail.campaigns.mailer' => 'throwing']);
    }

    public function test_a_send_that_failed_on_every_attempt_is_marked_failed_and_lets_the_campaign_finish(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');

        (new SendCampaignEmailJob($recipientId))->failed(new RuntimeException('The email could not be built.'));

        $this->assertSame(['FAILED', 'SEND_ERROR'], [$this->recipientStatus($recipientId), $this->failureReason($recipientId)]);
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_a_send_abandoned_while_the_email_was_going_out_is_recorded_as_possibly_delivered(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['status' => 'SENDING']);

        (new SendCampaignEmailJob($recipientId))->failed(null);

        $this->assertSame(['FAILED', 'TIMEOUT'], [$this->recipientStatus($recipientId), $this->failureReason($recipientId)]);
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_dispatching_a_campaign_whose_organizer_was_deleted_cancels_it_and_leaves_nobody_waiting(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        DB::table('organizers')->where('id', $this->organizerId)->update(['deleted_at' => now()]);

        app(CampaignDispatchService::class)->dispatch($campaignId);

        $campaign = $this->campaign($campaignId);
        $this->assertSame(['CANCELLED', CampaignDispatchService::REASON_ORGANIZER_DELETED], [$campaign->status, $campaign->status_reason]);
        $this->assertSame(['SKIPPED', 'CAMPAIGN_STOPPED'], [$this->recipientStatus($recipientId), $this->failureReason($recipientId)]);
    }

    public function test_recipients_stuck_in_sending_are_failed_after_fifteen_minutes(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subHour(), 'updated_at' => now()->subHour()]);
        $stuckId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['status' => 'SENDING', 'updated_at' => now()->subMinutes(16)]);
        $inFlightId = $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com', ['status' => 'SENDING', 'updated_at' => now()->subMinutes(14)]);
        $pendingId = $this->insertRecipient($campaignId, null, 'pending@example.com', ['status' => 'PENDING', 'updated_at' => now()->subHour()]);
        $sentId = $this->insertRecipient($campaignId, null, 'sent@example.com', ['status' => 'SENT', 'updated_at' => now()->subHour()]);

        SendScheduledCampaignsJob::dispatchSync();

        $this->assertSame(
            [$stuckId => 'FAILED', $inFlightId => 'SENDING', $pendingId => 'PENDING', $sentId => 'SENT'],
            DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->orderBy('id')->pluck('status', 'id')->all(),
        );
        $this->assertSame('TIMEOUT', DB::table('crm_campaign_recipients')->where('id', $stuckId)->value('failure_reason'));
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_sending_campaigns_with_no_recipient_activity_for_fifteen_minutes_are_dispatched_again(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $stale = ['sent_at' => now()->subHour(), 'updated_at' => now()->subMinutes(16)];
        $stalledId = $this->insertCampaign(['status' => 'SENDING', ...$stale]);
        $this->insertRecipient($stalledId, $this->adaId, 'ada@example.com', ['updated_at' => now()->subMinutes(16)]);
        $neverDispatchedId = $this->insertCampaign(['status' => 'SENDING', 'updated_at' => now()->subMinutes(16)]);
        $activeId = $this->insertCampaign(['status' => 'SENDING', ...$stale]);
        $this->insertRecipient($activeId, $this->adaId, 'ada@example.com', ['updated_at' => now()->subMinutes(16)]);
        $this->insertRecipient($activeId, $this->graceId, 'grace@example.com', ['status' => 'SENT', 'updated_at' => now()->subMinutes(14)]);
        $justStartedId = $this->insertCampaign(['status' => 'SENDING', 'updated_at' => now()->subMinutes(14)]);
        $this->insertRecipient($justStartedId, $this->adaId, 'ada@example.com', ['updated_at' => now()->subMinutes(16)]);
        foreach (['PAUSED', 'CANCELLED', 'SENT', 'PENDING_REVIEW', 'DRAFT'] as $status) {
            $idleId = $this->insertCampaign(['status' => $status, ...$stale]);
            $this->insertRecipient($idleId, $this->adaId, 'ada@example.com', ['updated_at' => now()->subMinutes(16)]);
        }

        SendScheduledCampaignsJob::dispatchSync();

        $dispatched = Bus::dispatched(DispatchCampaignJob::class)->map(static fn (DispatchCampaignJob $job) => $job->campaignId)->sort()->values()->all();
        $this->assertSame([$stalledId, $neverDispatchedId], $dispatched);
    }

    public function test_stalled_campaigns_are_picked_up_oldest_first_and_only_as_many_as_asked_for(): void
    {
        $stale = ['status' => 'SENDING', 'sent_at' => now()->subHour(), 'updated_at' => now()->subMinutes(16)];
        $stalledIds = [$this->insertCampaign($stale), $this->insertCampaign($stale), $this->insertCampaign($stale)];
        $repository = app(CrmCampaignRepositoryInterface::class);

        $this->assertSame(array_slice($stalledIds, 0, 2), $repository->findStalledCampaignIds(now()->subMinutes(15), 2));
        $this->assertSame($stalledIds, $repository->findStalledCampaignIds(now()->subMinutes(15), 10));
    }

    public function test_a_stalled_campaign_is_dispatched_again_only_once_per_stall_window(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $stalledSince = now()->subMinutes(16)->startOfSecond();
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subHour(), 'updated_at' => $stalledSince]);
        $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['updated_at' => $stalledSince]);

        SendScheduledCampaignsJob::dispatchSync();

        $campaign = $this->campaign($campaignId);
        $this->assertSame('SENDING', $campaign->status);
        $this->assertGreaterThanOrEqual(now()->subMinute()->toDateTimeString(), $campaign->updated_at);
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);

        $this->releaseDispatchLock($campaignId);
        SendScheduledCampaignsJob::dispatchSync();

        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
        Bus::assertDispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->campaignId === $campaignId);

        DB::table('crm_campaigns')->where('id', $campaignId)->update(['updated_at' => $stalledSince]);
        SendScheduledCampaignsJob::dispatchSync();

        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 2);
    }

    public function test_a_campaign_whose_last_recipient_got_stuck_finishes_in_the_same_run(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subHour(), 'updated_at' => now()->subHour()]);
        $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com', ['status' => 'SENT', 'updated_at' => now()->subHour()]);
        $stuckId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['status' => 'SENDING', 'updated_at' => now()->subMinutes(20)]);
        $stuckSince = DB::table('crm_campaign_recipients')->where('id', $stuckId)->value('updated_at');

        SendScheduledCampaignsJob::dispatchSync();

        $stuck = DB::table('crm_campaign_recipients')->where('id', $stuckId)->first();
        $this->assertSame(['FAILED', $stuckSince], [$stuck->status, $stuck->updated_at]);
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
        $this->assertCount(0, $this->sentEmails());
    }

    public function test_every_crm_job_declares_a_timeout_inside_the_queue_retry_window(): void
    {
        $retryAfter = (int) config('queue.connections.redis.retry_after');
        $jobs = array_filter(
            array_map(
                static fn (string $path) => 'HiEvents\\Enterprise\\Crm\\Jobs\\'.basename($path, '.php'),
                glob(base_path('ee/Crm/Jobs/*.php')),
            ),
            static fn (string $class) => class_exists($class),
        );

        $this->assertGreaterThan(15, count($jobs));

        foreach ($jobs as $job) {
            $timeout = (new ReflectionClass($job))->getDefaultProperties()['timeout'] ?? null;

            $this->assertIsInt($timeout, $job);
            $this->assertLessThanOrEqual($retryAfter - 15, $timeout, $job);
        }
    }

    public function test_dispatching_stamps_the_send_time_once_and_takes_no_new_snapshot(): void
    {
        Bus::fake([SendCampaignEmailJob::class]);
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'recipients_count' => 1]);
        $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com');

        app(CampaignDispatchService::class)->dispatch($campaignId);
        $sentAt = $this->campaign($campaignId)->sent_at;
        $this->travel(5)->minutes();
        app(CampaignDispatchService::class)->dispatch($campaignId);

        $this->assertNotNull($sentAt);
        $this->assertSame($sentAt, $this->campaign($campaignId)->sent_at);
        $this->assertSame(['grace@example.com'], $this->recipientEmails($campaignId));
        $this->assertSame(1, (int) $this->campaign($campaignId)->recipients_count);
    }

    public function test_recipients_are_snapshotted_when_the_campaign_is_launched_not_when_it_is_dispatched(): void
    {
        Bus::fake([DispatchCampaignJob::class, SendCampaignEmailJob::class]);
        $campaignId = $this->insertCampaign();

        $this->launch($campaignId);

        $this->assertSame(['ada@example.com', 'grace@example.com'], $this->recipientEmails($campaignId));
        $this->assertSame([2, null], [(int) $this->campaign($campaignId)->recipients_count, $this->campaign($campaignId)->sent_at]);

        $this->insertSubscribedContact('late@example.com');
        app(CampaignDispatchService::class)->dispatch($campaignId);

        $this->assertSame(['ada@example.com', 'grace@example.com'], $this->recipientEmails($campaignId));
        Bus::assertDispatchedTimes(SendCampaignEmailJob::class, 2);
    }

    public static function markdownBodies(): array
    {
        return [
            'link on its own paragraph' => ["<p>Hello</p>\n\n[click me](//evil.test/phish)\n\n<p>Bye</p>"],
            'image on its own paragraph' => ["<p>Hello</p>\n\n![tracker](//evil.test/pixel.png)\n\n<p>Bye</p>"],
            'link after windows line endings' => ["<p>Hello</p>\r\n\r\n[click me](//evil.test/phish)\r\n\r\n<p>Bye</p>"],
            'reference style link' => ["<p>Hello</p>\n\n[click me][1]\n\n[1]: //evil.test/phish\n\n<p>Bye</p>"],
            'autolink' => ["<p>Hello</p>\n\n<//evil.test/phish>\n\n<https://evil.test/phish>\n\n<p>Bye</p>"],
            'indented code block and heading' => ["<p>Hello</p>\n\n    [click me](//evil.test/phish)\n\n# [click me](//evil.test/phish)\n\n<p>Bye</p>"],
        ];
    }

    #[DataProvider('markdownBodies')]
    public function test_markdown_in_a_campaign_body_never_becomes_a_link_or_an_image(string $body): void
    {
        $campaignId = $this->insertCampaign(['body' => $body]);
        $campaign = app(CrmCampaignRepositoryInterface::class)->findById($campaignId);

        foreach ([null, $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com')] as $recipientId) {
            $html = app(CampaignMailFactory::class)->make($campaign, $recipientId)->render();

            $this->assertStringContainsString('Hello', $html);
            $this->assertStringContainsString('Bye', $html);
            $this->assertDoesNotMatchRegularExpression('/<a\b[^>]*evil\.test/i', $html);
            $this->assertDoesNotMatchRegularExpression('/<img\b[^>]*evil\.test/i', $html);
            $this->assertDoesNotMatchRegularExpression('/(href|src)\s*=\s*["\']?[^"\'>]*evil\.test/i', $html);
            $this->assertStringNotContainsString('<h1>[', $html);
        }
    }

    public static function organizerNamesWithMarkdown(): array
    {
        return [
            'link after a blank line' => ["Acme\n\n[Claim](https://evil.example)"],
            'link after windows line endings' => ["Acme\r\n\r\n[Claim](https://evil.example)"],
            'heading and autolink' => ["Acme\n\n# <https://evil.example>"],
            'image' => ["Acme\n\n![Claim](https://evil.example/pixel.png)"],
        ];
    }

    #[DataProvider('organizerNamesWithMarkdown')]
    public function test_markdown_in_an_organizer_name_never_becomes_a_link_or_an_image(string $organizerName): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update(['name' => $organizerName]);
        $campaignId = $this->insertCampaign();
        $campaign = app(CrmCampaignRepositoryInterface::class)->findById($campaignId);

        foreach ([null, $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com')] as $recipientId) {
            $html = app(CampaignMailFactory::class)->make($campaign, $recipientId)->render();

            $this->assertStringContainsString('Acme', $html);
            $this->assertStringContainsString('See you soon', $html);
            $this->assertDoesNotMatchRegularExpression('/<a\b[^>]*evil\.example/i', $html);
            $this->assertDoesNotMatchRegularExpression('/<img\b[^>]*evil\.example/i', $html);
            $this->assertDoesNotMatchRegularExpression('/(href|src)\s*=\s*["\']?[^"\'>]*evil\.example/i', $html);
            $this->assertDoesNotMatchRegularExpression('/<h1[^>]*>\s*(<|&lt;)https/i', $html);
        }
    }

    public function test_a_campaign_is_delivered_with_a_multi_line_organizer_name_on_one_line(): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update(['name' => "Acme\n\n[Claim](https://evil.example)"]);
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');

        app(CampaignDeliveryService::class)->deliver($recipientId);

        $html = $this->sentEmails()['ada@example.com']->getHtmlBody();
        $this->assertStringContainsString('Acme [Claim](https://evil.example)', $html);
        $this->assertDoesNotMatchRegularExpression('/<a\b[^>]*evil\.example/i', $html);
    }

    public static function healthSamples(): array
    {
        return [
            'one complaint in fifty' => [50, 1, 0, 'PAUSED', CampaignHealthGuard::REASON_COMPLAINTS],
            'five bounces in fifty' => [50, 0, 5, 'PAUSED', CampaignHealthGuard::REASON_BOUNCES],
            'four bounces in fifty is exactly the limit' => [50, 0, 4, 'SENDING', null],
            'complaints below fifty sends' => [49, 10, 10, 'SENDING', null],
            'healthy' => [60, 0, 2, 'SENDING', null],
        ];
    }

    #[DataProvider('healthSamples')]
    public function test_the_health_guard_pauses_a_sending_campaign_with_too_many_complaints_or_bounces(int $sent, int $complaints, int $bounces, string $expected, ?string $reason): void
    {
        $campaignId = $this->seedDeliveredCampaign('SENDING', $sent, $complaints, $bounces);

        $pendingId = $this->insertRecipient($campaignId, null, 'pending@example.com');

        $paused = app(CampaignHealthGuard::class)->stopIfUnhealthy($campaignId);

        $this->assertSame($expected === 'PAUSED', $paused);
        $this->assertSame($expected === 'PAUSED' ? 'SKIPPED' : 'PENDING', $this->recipientStatus($pendingId));
        $this->assertSame([$expected, $reason], [$this->campaign($campaignId)->status, $this->campaign($campaignId)->status_reason]);
    }

    public function test_the_health_guard_leaves_a_finished_campaign_alone(): void
    {
        $campaignId = $this->seedDeliveredCampaign('SENT', 50, 5, 10);

        $this->assertFalse(app(CampaignHealthGuard::class)->stopIfUnhealthy($campaignId));
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_the_health_guard_ignores_soft_bounces(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()]);
        foreach (range(1, CampaignHealthGuard::MINIMUM_SENDS) as $index) {
            $this->insertRecipient($campaignId, null, "r$index@example.com", ['status' => 'SENT', 'provider_message_id' => "m-$campaignId-$index"]);
            $this->insertDelivery("m-$campaignId-$index", $index <= 10 ? ['bounced_at' => now(), 'bounce_type' => 'TRANSIENT'] : ['delivered_at' => now()]);
        }

        $this->assertFalse(app(CampaignHealthGuard::class)->stopIfUnhealthy($campaignId));
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
    }

    public function test_delivery_stats_count_each_recipient_once_by_provider_message(): void
    {
        $campaignId = $this->seedDeliveredCampaign('SENT', 5, 1, 2);
        $this->insertRecipient($campaignId, null, 'skipped@example.com', ['status' => 'SKIPPED']);
        $this->insertRecipient($campaignId, null, 'failed@example.com', ['status' => 'FAILED']);
        $this->insertRecipient($campaignId, null, 'pending@example.com');
        $this->insertDelivery('unrelated-message', ['complained_at' => now()]);
        DB::table('email_deliveries')->insert(['provider_message_id' => null, 'bounced_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $stats = (array) app(CrmCampaignRepositoryInterface::class)->getDeliveryStats([$campaignId])->get($campaignId);

        $this->assertEquals(
            ['campaign_id' => $campaignId, 'recipients' => 8, 'sent' => 5, 'failed' => 1, 'skipped' => 1, 'pending' => 1, 'delivered' => 5, 'opened' => 0, 'clicked' => 0, 'bounced' => 2, 'complained' => 1, 'undelivered' => 0],
            $stats,
        );
    }

    public function test_hard_bounces_and_complaints_make_the_contact_unmailable_once(): void
    {
        Bus::fake();
        $transientId = $this->insertSubscribedContact('transient@example.com');
        $untouchedId = $this->insertSubscribedContact('untouched@example.com');
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour()]);
        $bouncedRecipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-ada']);
        $complainedRecipientId = $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-grace']);
        $this->insertRecipient($campaignId, $transientId, 'transient@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-transient']);
        $this->insertRecipient($campaignId, $untouchedId, 'untouched@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-untouched']);
        $this->insertDelivery('m-ada', ['bounced_at' => now(), 'bounce_type' => 'PERMANENT']);
        $this->insertDelivery('m-grace', ['delivered_at' => now(), 'complained_at' => now()]);
        $this->insertDelivery('m-transient', ['bounced_at' => now(), 'bounce_type' => 'TRANSIENT']);
        $this->insertDelivery('m-untouched', ['delivered_at' => now(), 'first_opened_at' => now()]);

        app(CampaignFeedbackService::class)->recordBouncesAndComplaints();
        app(CampaignFeedbackService::class)->recordBouncesAndComplaints();

        $this->assertSame(
            ['ada@example.com' => 'BOUNCED', 'grace@example.com' => 'COMPLAINED', 'transient@example.com' => 'SUBSCRIBED', 'untouched@example.com' => 'SUBSCRIBED'],
            DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->orderBy('email')->pluck('email_marketing_status', 'email')->all(),
        );
        $events = DB::table('crm_consent_events')->where('source_type', 'CAMPAIGN')->orderBy('contact_id')->get();
        $this->assertSame(
            [[$this->adaId, 'BOUNCED', $bouncedRecipientId], [$this->graceId, 'COMPLAINED', $complainedRecipientId]],
            $events->map(static fn (object $event) => [(int) $event->contact_id, $event->action, (int) $event->source_id])->all(),
        );

        $nextId = $this->insertCampaign();
        $this->launch($nextId);
        $this->assertSame(['transient@example.com', 'untouched@example.com'], $this->recipientEmails($nextId));
    }

    public function test_recording_feedback_drains_more_than_one_batch_in_a_run(): void
    {
        $count = 501;
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour()]);
        $this->insertContactRows(array_map(fn (int $index) => [
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'email' => "bulk$index@example.com",
            'source' => 'MANUAL',
            'email_marketing_status' => 'SUBSCRIBED',
            'created_at' => now(),
            'updated_at' => now(),
        ], range(1, $count)));
        $contactIds = DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->where('email', 'like', 'bulk%')->pluck('id', 'email');
        DB::table('crm_campaign_recipients')->insert($contactIds->map(fn (int $contactId, string $email) => [
            'campaign_id' => $campaignId,
            'organizer_id' => $this->organizerId,
            'contact_id' => $contactId,
            'email' => $email,
            'status' => 'SENT',
            'provider_message_id' => "m-$email",
            'created_at' => now(),
            'updated_at' => now(),
        ])->values()->all());
        DB::table('email_deliveries')->insert($contactIds->keys()->map(static fn (string $email) => [
            'provider_message_id' => "m-$email",
            'complained_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());

        app(CampaignFeedbackService::class)->recordBouncesAndComplaints();

        $this->assertSame($count, DB::table('crm_consent_events')->where('organizer_id', $this->organizerId)->where('source_type', 'CAMPAIGN')->where('action', 'COMPLAINED')->count());
        $this->assertCount(0, app(CrmCampaignRepositoryInterface::class)->findUnrecordedFeedback(now()->subDay(), 1));
    }

    public static function feedbackAfterDeletion(): array
    {
        return [
            'a complaint' => [null, ['complained_at' => '2026-01-01 10:00:00'], 'COMPLAINED'],
            'a hard bounce' => [null, ['bounced_at' => '2026-01-01 10:00:00', 'bounce_type' => 'PERMANENT'], 'BOUNCED'],
            'a hard bounce of someone who had unsubscribed' => ['OPT_OUT', ['bounced_at' => '2026-01-01 10:00:00', 'bounce_type' => 'PERMANENT'], 'UNSUBSCRIBED'],
            'a complaint from someone who had unsubscribed' => ['OPT_OUT', ['complained_at' => '2026-01-01 10:00:00'], 'COMPLAINED'],
            'a hard bounce of someone who had complained' => ['COMPLAINED', ['bounced_at' => '2026-01-01 10:00:00', 'bounce_type' => 'PERMANENT'], 'COMPLAINED'],
            'a soft bounce' => [null, ['bounced_at' => '2026-01-01 10:00:00', 'bounce_type' => 'TRANSIENT'], 'SUBSCRIBED'],
        ];
    }

    #[DataProvider('feedbackAfterDeletion')]
    public function test_feedback_arriving_after_the_contact_was_deleted_still_suppresses_them_when_they_are_added_again(?string $consentBeforeDeletion, array $delivery, string $statusOnceRestored): void
    {
        $this->projectOrders($this->insertOrder(['email' => 'ada@example.com', 'opted_into_marketing_at' => now()->subDays(40)]));
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour()]);
        $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-ada']);
        if ($consentBeforeDeletion !== null) {
            $this->insertConsent($this->adaId, 'ada@example.com', $consentBeforeDeletion, now()->subDay());
        }
        app(DeletedContactService::class)->delete($this->organizerId, [$this->adaId]);
        $this->insertDelivery('m-ada', $delivery);

        app(CampaignFeedbackService::class)->recordBouncesAndComplaints();
        app(CampaignFeedbackService::class)->recordBouncesAndComplaints();

        $this->assertNull($this->contact('ada@example.com'));
        $this->assertCount(0, app(CrmCampaignRepositoryInterface::class)->findUnrecordedFeedback(now()->subDay(), 1));

        $restored = app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: 'ada@example.com',
            restore_deleted: true,
        ));

        $this->assertSame($statusOnceRestored, $restored->getEmailMarketingStatus());
    }

    public function test_feedback_for_a_campaign_sent_before_the_contact_was_deleted_and_added_again_suppresses_the_restored_contact(): void
    {
        $this->projectOrders($this->insertOrder(['email' => 'ada@example.com', 'opted_into_marketing_at' => now()->subDays(40)]));
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour()]);
        $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-ada']);
        app(DeletedContactService::class)->delete($this->organizerId, [$this->adaId]);
        app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: 'ada@example.com',
            restore_deleted: true,
        ));
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
        $this->insertDelivery('m-ada', ['complained_at' => now()]);

        app(CampaignFeedbackService::class)->recordBouncesAndComplaints();

        $this->assertSame('COMPLAINED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_a_bounce_recorded_after_the_campaign_finished_shows_in_the_next_report(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour()]);
        $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-ada']);
        $campaigns = app(CrmCampaignRepositoryInterface::class);
        $reports = app(CampaignReportService::class);
        $before = $campaigns->findById($campaignId);
        $reports->attachDetail($before, false);
        $this->assertSame(0, (int) $before->getDeliveryStats()->bounced);
        $this->insertDelivery('m-ada', ['bounced_at' => now(), 'bounce_type' => 'PERMANENT']);

        app(CampaignFeedbackService::class)->recordBouncesAndComplaints();

        $after = $campaigns->findById($campaignId);
        $reports->attachDetail($after, false);
        $this->assertSame(1, (int) $after->getDeliveryStats()->bounced);
    }

    public function test_the_feedback_job_records_campaign_feedback(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour()]);
        $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-ada']);
        $this->insertDelivery('m-ada', ['complained_at' => now()]);

        RecordCampaignFeedbackJob::dispatchSync();

        $this->assertSame('COMPLAINED', DB::table('crm_contacts')->where('id', $this->adaId)->value('email_marketing_status'));
    }

    public function test_feedback_recorded_before_the_last_run_is_not_checked_again(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subDays(2)]);
        $oldRecipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-ada']);
        $newRecipientId = $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-grace']);
        $this->insertDelivery('m-ada', ['bounced_at' => now()->subDay(), 'bounce_type' => 'PERMANENT', 'updated_at' => now()->subDay()]);

        $this->travel(-1)->hours();
        app(CampaignFeedbackService::class)->recordBouncesAndComplaints();
        $this->travelBack();
        DB::table('crm_consent_events')->where('source_type', 'CAMPAIGN')->where('source_id', $oldRecipientId)->delete();
        $this->insertDelivery('m-grace', ['complained_at' => now()]);

        app(CampaignFeedbackService::class)->recordBouncesAndComplaints();

        $this->assertSame(
            [$newRecipientId],
            DB::table('crm_consent_events')->where('source_type', 'CAMPAIGN')->pluck('source_id')->map(static fn ($id) => (int) $id)->all(),
        );
        $this->assertSame(1, app(CrmCampaignRepositoryInterface::class)->findUnrecordedFeedback(now()->subDays(3), 10)->count());
        $this->assertSame(0, app(CrmCampaignRepositoryInterface::class)->findUnrecordedFeedback(now()->subHours(2), 10)->count());
    }

    public function test_each_due_scheduled_campaign_is_launched_by_its_own_job(): void
    {
        Bus::fake([LaunchScheduledCampaignJob::class]);
        $firstId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinutes(2)]);
        $secondId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute()]);
        $futureId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->addHour()]);
        $draftId = $this->insertCampaign(['status' => 'DRAFT', 'scheduled_at' => now()->subMinute()]);

        SendScheduledCampaignsJob::dispatchSync();

        $launched = Bus::dispatched(LaunchScheduledCampaignJob::class)->map(static fn (LaunchScheduledCampaignJob $job) => $job->campaignId);
        $this->assertSame([$firstId, $secondId], $launched->intersect([$firstId, $secondId, $futureId, $draftId])->sort()->values()->all());
        $this->assertSame(['SCHEDULED', 'SCHEDULED'], [$this->campaign($firstId)->status, $this->campaign($secondId)->status]);
    }

    public function test_recipients_of_a_sending_campaign_share_its_event_details_per_language(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()->subMinute(), 'event_id' => $this->eventId, 'body' => '<p>See you on {{ event_date }}</p>']);
        $campaign = app(CrmCampaignRepositoryInterface::class)->findById($campaignId);
        $adaRecipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');
        $graceRecipientId = $this->insertRecipient($campaignId, $this->graceId, 'grace@example.com');
        $eventQueries = function (callable $build): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $build();
            DB::disableQueryLog();

            return collect(DB::getQueryLog())->filter(static fn (array $query) => preg_match('/"(events|event_occurrences|promo_codes)"/', $query['query']) === 1)->count();
        };

        $first = $eventQueries(fn () => app(CampaignMailFactory::class)->make($campaign, $adaRecipientId)->render());
        $second = $eventQueries(fn () => app(CampaignMailFactory::class)->make($campaign, $graceRecipientId)->render());
        App::setLocale('de');
        try {
            $german = $eventQueries(fn () => app(CampaignMailFactory::class)->make($campaign, $graceRecipientId)->render());
        } finally {
            App::setLocale('en');
        }
        $preview = $eventQueries(fn () => app(CampaignMailFactory::class)->make($campaign, null)->render());

        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $second);
        $this->assertGreaterThan(0, $german);
        $this->assertGreaterThan(0, $preview);
    }

    public function test_due_scheduled_campaigns_are_launched_and_unsendable_ones_return_to_draft_with_a_reason(): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        $dueId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute()]);
        $futureId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->addHour()]);
        $emptyId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute(), 'segment_definition' => json_encode(['match' => 'all', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 50]]])]);
        $orphanedId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute(), 'segment_id' => 999999999]);
        $draftId = $this->insertCampaign(['status' => 'DRAFT', 'scheduled_at' => now()->subMinute()]);

        SendScheduledCampaignsJob::dispatchSync();

        $this->assertSame(['SENDING', null], [$this->campaign($dueId)->status, $this->campaign($dueId)->scheduled_at]);
        $this->assertSame('SCHEDULED', $this->campaign($futureId)->status);
        $this->assertSame('DRAFT', $this->campaign($draftId)->status);
        foreach ([$emptyId, $orphanedId] as $unsendableId) {
            $campaign = $this->campaign($unsendableId);
            $this->assertSame(['DRAFT', null], [$campaign->status, $campaign->scheduled_at]);
            $this->assertNotEmpty($campaign->status_reason);
        }
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
        Bus::assertDispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->campaignId === $dueId);
    }

    public function test_a_scheduled_campaign_that_cannot_be_sent_returns_to_draft_and_tells_its_creator(): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        Mail::fake();
        $campaignId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute(), 'segment_id' => 999999999]);

        app(CampaignLaunchService::class)->launchScheduled($campaignId, now());

        $campaign = $this->campaign($campaignId);
        $this->assertSame(['DRAFT', null, null], [$campaign->status, $campaign->scheduled_at, $campaign->approved_at]);
        $this->assertNotEmpty($campaign->status_reason);
        Mail::assertQueued(CampaignNotSentMail::class);
        Bus::assertNothingDispatched();
    }

    public function test_a_campaign_scheduled_for_exactly_now_is_launched(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->freezeSecond();
        $dueId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()]);

        app(CampaignLaunchService::class)->launchScheduled($dueId, now());

        $this->assertSame(['SENDING', null], [$this->campaign($dueId)->status, $this->campaign($dueId)->scheduled_at]);
        Bus::assertDispatchedTimes(DispatchCampaignJob::class, 1);
    }

    public static function changesAfterBeingListedAsDue(): array
    {
        return [
            'cancelled back to a draft' => ['DRAFT', null],
            'rescheduled for later' => ['SCHEDULED', 60],
            'rescheduled for a second from now' => ['SCHEDULED', 0],
            'deleted' => [null, null],
        ];
    }

    #[DataProvider('changesAfterBeingListedAsDue')]
    public function test_a_scheduled_campaign_changed_between_being_listed_and_launched_is_not_sent_and_keeps_its_new_state(?string $status, ?int $rescheduledInMinutes): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        $this->freezeSecond();
        $campaignId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute()]);
        $this->assertContains($campaignId, app(CrmCampaignRepositoryInterface::class)->findDueScheduledIds(now(), 500));
        $scheduledAt = $rescheduledInMinutes === null ? null : now()->addMinutes($rescheduledInMinutes)->addSecond()->toDateTimeString();

        if ($status === null) {
            DB::table('crm_campaigns')->where('id', $campaignId)->delete();
        } else {
            DB::table('crm_campaigns')->where('id', $campaignId)->update(['status' => $status, 'scheduled_at' => $scheduledAt]);
        }

        app(CampaignLaunchService::class)->launchScheduled($campaignId, now());

        $campaign = $this->campaign($campaignId);
        $this->assertSame(
            $status === null ? null : [$status, $scheduledAt, null],
            $campaign === null ? null : [$campaign->status, $campaign->scheduled_at, $campaign->status_reason],
        );
        $this->assertSame([], $this->recipientEmails($campaignId));
        Bus::assertNothingDispatched();
    }

    public static function launchableStatuses(): array
    {
        return [
            'draft' => ['DRAFT', false],
            'rejected' => ['REJECTED', false],
            'scheduled' => ['SCHEDULED', true],
        ];
    }

    #[DataProvider('launchableStatuses')]
    public function test_a_launch_that_dies_while_snapshotting_recipients_puts_the_campaign_back_and_can_be_retried(string $status, bool $isScheduled): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        $this->freezeSecond();
        $scheduledAt = $isScheduled ? now()->subMinute()->toDateTimeString() : null;
        $campaignId = $this->insertCampaign(['status' => $status, 'scheduled_at' => $scheduledAt]);
        $repository = Mockery::mock(app(CrmCampaignRepositoryInterface::class));
        $repository->shouldReceive('snapshotRecipients')->once()->andThrow(new RuntimeException('The database went away.'));
        $this->app->instance(CrmCampaignRepositoryInterface::class, $repository);

        try {
            $this->launchAs($campaignId, $isScheduled);
            $this->fail('The campaign was launched.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The database went away.', $exception->getMessage());
        }

        $this->assertSame([$status, $scheduledAt], [$this->campaign($campaignId)->status, $this->campaign($campaignId)->scheduled_at]);
        $this->assertSame([], $this->recipientEmails($campaignId));
        Bus::assertNothingDispatched();

        $this->app->forgetInstance(CrmCampaignRepositoryInterface::class);
        $this->launchAs($campaignId, $isScheduled);
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        $this->assertSame(['ada@example.com', 'grace@example.com'], $this->recipientEmails($campaignId));
    }

    #[DataProvider('launchableStatuses')]
    public function test_a_launch_that_dies_while_creating_the_early_access_code_puts_the_campaign_back(string $status, bool $isScheduled): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        $this->freezeSecond();
        $scheduledAt = $isScheduled ? now()->subMinute()->toDateTimeString() : null;
        $campaignId = $this->insertCampaign(['status' => $status, 'scheduled_at' => $scheduledAt, 'event_id' => $this->eventId, 'early_access_product_ids' => json_encode([$this->productId])]);
        $earlyAccess = Mockery::mock(EarlyAccessService::class);
        $earlyAccess->shouldReceive('findOfferableTickets')->andReturn(collect([new ProductDomainObject]));
        $earlyAccess->shouldReceive('createAccessCode')->once()->andThrow(new RuntimeException('The database went away.'));
        $this->app->instance(EarlyAccessService::class, $earlyAccess);

        try {
            $this->launchAs($campaignId, $isScheduled);
            $this->fail('The campaign was launched.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The database went away.', $exception->getMessage());
        }

        $campaign = $this->campaign($campaignId);
        $this->assertSame([$status, $scheduledAt, null], [$campaign->status, $campaign->scheduled_at, $campaign->promo_code_id]);
        $this->assertSame([], $this->recipientEmails($campaignId));
        Bus::assertNothingDispatched();
    }

    public function test_a_scheduled_early_access_campaign_whose_event_cannot_offer_it_returns_to_draft_with_the_reason(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $campaignId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute(), 'event_id' => $this->eventId, 'early_access_product_ids' => json_encode([$this->productId])]);

        SendScheduledCampaignsJob::dispatchSync();

        $campaign = $this->campaign($campaignId);
        $this->assertSame(['DRAFT', null, null], [$campaign->status, $campaign->scheduled_at, $campaign->promo_code_id]);
        $this->assertSame('SCHEDULED_SEND_FAILED', $campaign->status_reason);
        $this->assertSame([], $this->recipientEmails($campaignId));
        Bus::assertNothingDispatched();
    }

    public static function linkedRecordsDeletedAfterLaunch(): array
    {
        return [
            'promo code deleted' => ['promo_code_soft', true],
            'promo code row gone' => ['promo_code_hard', true],
            'event deleted' => ['event_soft', false],
            'event row unknown' => ['event_unknown', false],
        ];
    }

    #[DataProvider('linkedRecordsDeletedAfterLaunch')]
    public function test_a_campaign_still_sends_after_its_linked_event_or_promo_code_is_deleted(string $deleted, bool $linksToEvent): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $promoCodeId = DB::table('promo_codes')->insertGetId(['event_id' => $this->eventId, 'code' => 'early-existing', 'discount' => 0, 'discount_type' => 'NONE', 'created_at' => now(), 'updated_at' => now()]);
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now(), 'event_id' => $this->eventId, 'promo_code_id' => $promoCodeId]);
        $recipientId = $this->insertRecipient($campaignId, $this->adaId, 'ada@example.com');

        match ($deleted) {
            'promo_code_soft' => DB::table('promo_codes')->where('id', $promoCodeId)->update(['deleted_at' => now()]),
            'promo_code_hard' => DB::table('promo_codes')->where('id', $promoCodeId)->delete(),
            'event_soft' => DB::table('events')->where('id', $this->eventId)->update(['deleted_at' => now()]),
            'event_unknown' => DB::table('crm_campaigns')->where('id', $campaignId)->update(['event_id' => 999999999]),
        };

        app(CampaignDeliveryService::class)->deliver($recipientId);

        $this->assertSame('SENT', DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('status'));
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
        $html = $this->sentEmails()['ada@example.com']->getHtmlBody();
        $this->assertStringContainsString('See you soon', $html);
        $this->assertStringNotContainsString('promo_code', $html);
        $this->assertStringNotContainsString('early-existing', $html);
        $this->assertStringNotContainsString('Get early access', $html);
        if ($linksToEvent) {
            $this->assertStringContainsString('/event/'.$this->eventId.'/main-event', $html);
            $this->assertStringContainsString('Get tickets', $html);
        } else {
            $this->assertStringNotContainsString('/event/', $html);
            $this->assertStringNotContainsString('Get tickets', $html);
        }
    }

    public function test_a_scheduled_campaign_waits_for_review_in_saas_mode(): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        config(['app.saas_mode_enabled' => true]);
        $dueId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute()]);

        SendScheduledCampaignsJob::dispatchSync();

        $this->assertSame(['PENDING_REVIEW', 'FIRST_CAMPAIGN'], [$this->campaign($dueId)->status, $this->campaign($dueId)->status_reason]);
        Bus::assertDispatched(NotifyCampaignPendingReviewJob::class);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_a_due_campaign_of_an_account_without_the_crm_flag_is_left_scheduled(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, false);
        $dueId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute()]);

        SendScheduledCampaignsJob::dispatchSync();

        $this->assertSame('SCHEDULED', $this->campaign($dueId)->status);
        Bus::assertNothingDispatched();
    }

    #[DataProvider('overdueScheduledCampaigns')]
    public function test_a_scheduled_campaign_is_only_sent_within_hours_of_its_time(int $minutesLate, bool $crmEnabled, string $expectedStatus): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        Mail::fake();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, $crmEnabled);
        $campaignId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinutes($minutesLate)]);

        app(CampaignLaunchService::class)->launchScheduled($campaignId, now());

        $campaign = $this->campaign($campaignId);
        $this->assertSame($expectedStatus, $campaign->status);

        if ($expectedStatus === 'DRAFT') {
            $this->assertNull($campaign->scheduled_at);
            $this->assertSame('SCHEDULED_SEND_EXPIRED', $campaign->status_reason);
            $this->assertSame(0, DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->count());
            Mail::assertQueued(CampaignNotSentMail::class);
            Bus::assertNotDispatched(DispatchCampaignJob::class);
        } else {
            Mail::assertNothingQueued();
        }
    }

    public function test_without_a_licence_only_campaigns_past_their_send_window_are_picked_up_and_returned_to_draft(): void
    {
        Bus::fake([DispatchCampaignJob::class, LaunchScheduledCampaignJob::class]);
        $window = CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS * 60;
        $dueId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinutes($window - 5)]);
        $missedId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinutes($window + 5)]);
        $this->useLicenceKey(null);

        SendScheduledCampaignsJob::dispatchSync();

        $picked = Bus::dispatched(LaunchScheduledCampaignJob::class)->map(static fn (LaunchScheduledCampaignJob $job) => $job->campaignId)->all();
        $this->assertSame([$missedId], $picked);

        Mail::fake();
        app(CampaignLaunchService::class)->launchScheduled($missedId, now());
        app(CampaignLaunchService::class)->launchScheduled($dueId, now());

        $this->assertSame(['SCHEDULED', 'DRAFT'], [$this->campaign($dueId)->status, $this->campaign($missedId)->status]);
        Mail::assertQueued(CampaignNotSentMail::class, 1);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_a_scheduled_send_that_fails_stores_a_reason_code_and_tells_the_creator_why_in_their_language(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        Mail::fake();
        DB::table('users')->where('id', $this->owner->id)->update(['locale' => 'de']);
        $orphanedId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute(), 'segment_id' => 999999999, 'created_by_user_id' => $this->owner->id]);
        $emptyId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute(), 'created_by_user_id' => $this->owner->id, 'segment_definition' => json_encode(['match' => 'all', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 50]]])]);
        $expected = [];
        App::setLocale('de');
        $expected[$orphanedId] = __('The segment this campaign uses no longer exists. Choose who to send it to again.');
        $expected[$emptyId] = __('Nobody in this segment has opted in to marketing emails, so there is no one to send to.');
        App::setLocale('en');

        app(CampaignLaunchService::class)->launchScheduled($orphanedId, now());
        app(CampaignLaunchService::class)->launchScheduled($emptyId, now());

        $this->assertSame(['SCHEDULED_SEND_FAILED', 'NO_RECIPIENTS'], [$this->campaign($orphanedId)->status_reason, $this->campaign($emptyId)->status_reason]);
        $this->assertSame('en', App::getLocale());
        $reasons = Mail::queued(CampaignNotSentMail::class)
            ->map(fn (CampaignNotSentMail $mail) => (new ReflectionClass($mail))->getProperty('reason')->getValue($mail))
            ->all();
        $this->assertSame(array_values($expected), $reasons);
    }

    public static function overdueScheduledCampaigns(): array
    {
        $window = CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS * 60;

        return [
            'late but inside the window' => [$window - 5, true, 'SENDING'],
            'past the window' => [$window + 5, true, 'DRAFT'],
            'past the window while the feature is off' => [$window + 5, false, 'DRAFT'],
            'inside the window while the feature is off' => [$window - 5, false, 'SCHEDULED'],
        ];
    }

    public function test_an_early_access_campaign_hides_the_chosen_tickets_and_links_to_a_code_for_them(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        [$publicProductId] = $this->insertTicket($this->eventId);
        $campaignId = $this->insertCampaign(['event_id' => $this->eventId, 'early_access_product_ids' => json_encode([$this->productId])]);

        $status = $this->launch($campaignId);

        $this->assertSame('SENDING', $status->value);
        $this->assertTrue((bool) DB::table('products')->where('id', $this->productId)->value('is_hidden_without_promo_code'));
        $this->assertFalse((bool) DB::table('products')->where('id', $publicProductId)->value('is_hidden_without_promo_code'));
        $campaign = $this->campaign($campaignId);
        $this->assertSame('SENT', $campaign->status);
        $promoCode = DB::table('promo_codes')->where('id', $campaign->promo_code_id)->sole();
        $this->assertSame($this->eventId, (int) $promoCode->event_id);
        $this->assertStringStartsWith('early-', $promoCode->code);
        $this->assertEquals([$this->productId], json_decode($promoCode->applicable_product_ids, true));
        $this->assertSame('NONE', $promoCode->discount_type);
        $this->assertEquals(0, $promoCode->discount);
        $this->assertSame(1, DB::table('promo_codes')->where('event_id', $this->eventId)->count());

        $html = $this->sentEmails()['ada@example.com']->getHtmlBody();
        $this->assertStringContainsString('/event/'.$this->eventId.'/main-event?promo_code='.$promoCode->code, $html);
    }

    public function test_ending_early_access_leaves_tickets_that_were_hidden_before_the_campaign_hidden(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        [$alreadyHiddenProductId] = $this->insertTicket($this->eventId);
        DB::table('products')->where('id', $alreadyHiddenProductId)->update(['is_hidden_without_promo_code' => true]);
        $campaignId = $this->insertCampaign(['event_id' => $this->eventId, 'early_access_product_ids' => json_encode([$this->productId, $alreadyHiddenProductId])]);

        $this->launch($campaignId);

        $this->assertEquals([$this->productId], json_decode($this->campaign($campaignId)->early_access_hidden_product_ids, true));

        $this->endEarlyAccess($campaignId);

        $this->assertFalse((bool) DB::table('products')->where('id', $this->productId)->value('is_hidden_without_promo_code'));
        $this->assertTrue((bool) DB::table('products')->where('id', $alreadyHiddenProductId)->value('is_hidden_without_promo_code'));
        $this->assertEquals([], json_decode($this->campaign($campaignId)->early_access_hidden_product_ids, true));
    }

    public function test_a_ticket_two_campaigns_give_early_access_to_stays_hidden_until_both_end(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $firstId = $this->insertCampaign(['event_id' => $this->eventId, 'early_access_product_ids' => json_encode([$this->productId])]);
        $secondId = $this->insertCampaign(['event_id' => $this->eventId, 'early_access_product_ids' => json_encode([$this->productId])]);
        $this->launch($firstId);
        $this->launch($secondId);
        $repository = app(CrmCampaignRepositoryInterface::class);

        $this->assertSame([], app(EarlyAccessService::class)->releasableProductIds($repository->findById($firstId)));

        $this->endEarlyAccess($firstId);

        $this->assertTrue((bool) DB::table('products')->where('id', $this->productId)->value('is_hidden_without_promo_code'));
        $this->assertSame([$this->productId], app(EarlyAccessService::class)->releasableProductIds($repository->findById($secondId)));

        $this->endEarlyAccess($secondId);

        $this->assertFalse((bool) DB::table('products')->where('id', $this->productId)->value('is_hidden_without_promo_code'));
    }

    public function test_relaunching_a_rejected_early_access_campaign_reuses_its_code(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        DB::table('products')->where('id', $this->productId)->update(['is_hidden_without_promo_code' => true]);
        $promoCodeId = DB::table('promo_codes')->insertGetId(['event_id' => $this->eventId, 'code' => 'early-existing', 'discount' => 0, 'discount_type' => 'NONE', 'created_at' => now(), 'updated_at' => now()]);
        $campaignId = $this->insertCampaign(['status' => 'REJECTED', 'event_id' => $this->eventId, 'promo_code_id' => $promoCodeId]);

        $this->launch($campaignId);

        $this->assertSame($promoCodeId, (int) $this->campaign($campaignId)->promo_code_id);
        $this->assertSame(1, DB::table('promo_codes')->where('event_id', $this->eventId)->count());
    }

    public static function unusableEarlyAccessEvents(): array
    {
        return [
            'event is not live' => ['draft'],
            'early access tickets were deleted' => ['tickets_deleted'],
            'event belongs to another organizer' => ['foreign'],
            'event was deleted' => ['deleted'],
        ];
    }

    #[DataProvider('unusableEarlyAccessEvents')]
    public function test_an_early_access_campaign_is_refused_when_its_event_cannot_offer_early_access(string $case): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $eventId = $this->eventId;
        DB::table('events')->where('id', $eventId)->update(['status' => $case === 'draft' ? 'DRAFT' : 'LIVE']);
        if ($case === 'tickets_deleted') {
            DB::table('products')->where('event_id', $eventId)->update(['deleted_at' => now()]);
        }

        if ($case === 'foreign') {
            $eventId = $this->createEvent($this->accountId, $this->createOrganizer($this->accountId, 'Other Organizer'), 'Foreign Event');
            DB::table('events')->where('id', $eventId)->update(['status' => 'LIVE']);
            [$foreignProductId] = $this->insertTicket($eventId);
            DB::table('products')->where('id', $foreignProductId)->update(['is_hidden_without_promo_code' => true]);
        }
        if ($case === 'deleted') {
            DB::table('events')->where('id', $eventId)->update(['deleted_at' => now()]);
        }

        $campaignId = $this->insertCampaign(['event_id' => $eventId, 'early_access_product_ids' => json_encode([$case === 'foreign' ? $foreignProductId : $this->productId])]);

        try {
            $this->launch($campaignId);
            $this->fail('The campaign was launched.');
        } catch (CampaignNotSendableException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $campaign = $this->campaign($campaignId);
        $this->assertSame(['DRAFT', null], [$campaign->status, $campaign->promo_code_id]);
        $this->assertSame(0, DB::table('promo_codes')->count());
        Bus::assertNothingDispatched();
    }

    private function releaseDispatchLock(int $campaignId): void
    {
        (new UniqueLock(app(CacheRepository::class)))->release(new DispatchCampaignJob($campaignId));
    }

    private function seedDeliveredCampaign(string $status, int $sent, int $complaints, int $bounces): int
    {
        $campaignId = $this->insertCampaign(['status' => $status, 'sent_at' => now()->subMinutes(10)]);
        $now = now();
        $recipients = [];
        $deliveries = [];

        foreach (range(1, $sent) as $index) {
            $messageId = "campaign-$campaignId-message-$index";
            $recipients[] = [
                'campaign_id' => $campaignId,
                'organizer_id' => $this->organizerId,
                'email' => "recipient$index@example.com",
                'status' => 'SENT',
                'provider_message_id' => $messageId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $deliveries[] = [
                'provider_message_id' => $messageId,
                'delivered_at' => $now,
                'complained_at' => $index <= $complaints ? $now : null,
                'bounced_at' => $index > $sent - $bounces ? $now : null,
                'bounce_type' => $index > $sent - $bounces ? 'PERMANENT' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('crm_campaign_recipients')->insert($recipients);
        DB::table('email_deliveries')->insert($deliveries);

        return $campaignId;
    }

    private function insertSavedSegment(array $rules): int
    {
        return DB::table('crm_segments')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => 'Saved',
            'definition' => json_encode(['match' => 'all', 'rules' => $rules]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recipientStatus(int $recipientId): string
    {
        return DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('status');
    }

    private function failureReason(int $recipientId): ?string
    {
        return DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('failure_reason');
    }

    private function launchAs(int $campaignId, bool $isScheduled): void
    {
        if ($isScheduled) {
            app(CampaignLaunchService::class)->launchScheduled($campaignId, now());

            return;
        }

        $this->launch($campaignId);
    }

    private function endEarlyAccess(int $campaignId): void
    {
        app(EarlyAccessService::class)->makePublic(app(CrmCampaignRepositoryInterface::class)->findById($campaignId));
    }

    private function launch(int $campaignId): CrmCampaignStatus
    {
        return app(CampaignLaunchService::class)->launch(app(CrmCampaignRepositoryInterface::class)->findById($campaignId));
    }

    /**
     * @return string[]
     */
    private function recipientEmails(int $campaignId): array
    {
        return DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->orderBy('email')->pluck('email')->all();
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
