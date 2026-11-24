<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Carbon\Carbon;
use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Enums\SmsCountry;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPendingReviewJob;
use HiEvents\Enterprise\Crm\Jobs\SendCampaignSmsJob;
use HiEvents\Enterprise\Crm\Mail\CampaignNotSentMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\CreateContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DeleteContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\UnsubscribeContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\DTO\PreviewSmsCampaignDraftDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\PreviewSmsCampaignDraftHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\SendSmsTestHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDispatchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeTokenService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\EmailMask;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\CampaignSmsComposer;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\FakeSmsProvider;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsCampaignDeliveryService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsCredentialsDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsPreviewDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsPreviewVariantDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsProviderInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsSenderDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsWebhookService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\TwilioSmsProvider;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmsCampaignTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    private const SERVICE_SID = 'MG00000000000000000000000000000001';

    private const AUTH_TOKEN = 'test-auth-token';

    private object $provider;

    private int $irishId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        config(['app.saas_mode_enabled' => false]);
        $this->setUpCrmOrganizer();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, true);
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);

        $this->provider = $this->fakeProvider();
        $this->app->instance(SmsProviderInterface::class, $this->provider);

        app(SmsConnectionService::class)->save($this->accountId, $this->organizerId, 'AC'.str_repeat('1', 32), self::AUTH_TOKEN, self::SERVICE_SID, ['IE', 'US']);

        $this->irishId = $this->textSubscriber('ada@example.com', '+353851234567', 'IE');
    }

    public function test_only_text_subscribers_in_enabled_countries_are_snapshotted(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->insertContact('aussie@example.com', ['phone' => '+61412345678', 'phone_country' => 'AU', 'sms_marketing_status' => 'SUBSCRIBED']);
        $this->insertContact('quiet@example.com', ['phone' => '+353851111111', 'phone_country' => 'IE', 'sms_marketing_status' => 'NOT_SUBSCRIBED']);
        $this->insertContact('nophone@example.com', ['sms_marketing_status' => 'SUBSCRIBED']);

        $this->launch($this->smsCampaign());

        $recipients = DB::table('crm_campaign_recipients')->get();
        $this->assertSame(['+353851234567'], $recipients->pluck('phone')->all());
        $this->assertSame(['IE'], $recipients->pluck('phone_country')->all());
    }

    public function test_an_irish_text_carries_the_organizer_name_and_an_opt_out_link(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $campaignId = $this->smsCampaign(['body' => 'Hi {{ first_name }}, tickets are on sale']);
        $this->launch($campaignId);
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');

        app(SmsCampaignDeliveryService::class)->deliver($recipientId);

        $this->assertCount(1, $this->provider->sent);
        $this->assertSame('+353851234567', $this->provider->sent[0]['to']);
        $organizer = DB::table('organizers')->where('id', $this->organizerId)->value('name');
        $this->assertStringStartsWith($organizer.': Hi Ada, tickets are on sale', $this->provider->sent[0]['body']);
        $this->assertStringContainsString(app(CampaignUnsubscribeTokenService::class)->smsUrl($recipientId), $this->provider->sent[0]['body']);
        $this->assertSame('SENT', DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('status'));
    }

    public function test_with_the_fake_driver_a_delivered_text_can_be_read_back_exactly_as_the_recipient_got_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $this->app->forgetInstance(SmsProviderInterface::class);
        $campaignId = $this->smsCampaign(['body' => 'Hi {{ first_name }}, tickets are on sale']);
        $this->launch($campaignId);
        $recipient = DB::table('crm_campaign_recipients')->first();

        app(SmsCampaignDeliveryService::class)->deliver((int) $recipient->id);

        $organizer = DB::table('organizers')->where('id', $this->organizerId)->value('name');
        $this->assertSame(
            [[
                'message_id' => DB::table('crm_campaign_recipients')->where('id', $recipient->id)->value('provider_message_id'),
                'body' => $organizer.": Hi Ada, tickets are on sale\nOpt out: ".app(CampaignUnsubscribeTokenService::class)->smsUrl((int) $recipient->id),
            ]],
            app(SmsProviderInterface::class)->sentTo('AC'.str_repeat('1', 32), '+353851234567'),
        );
    }

    public function test_without_the_fake_driver_there_are_no_sent_texts_to_read(): void
    {
        $this->app->forgetInstance(SmsProviderInterface::class);
        app(SmsProviderInterface::class)->send(new SmsCredentialsDTO('AC'.str_repeat('1', 32), self::AUTH_TOKEN), '+353851234567', 'Hello', null);
        config(['services.sms.driver' => 'twilio']);

        $this->assertInstanceOf(TwilioSmsProvider::class, app(SmsProviderInterface::class));
        $this->assertFalse(method_exists(app(SmsProviderInterface::class), 'sentTo'));
        $this->expectException(SmsProviderException::class);
        app(FakeSmsProvider::class)->sentTo('AC'.str_repeat('1', 32), '+353851234567');
    }

    public function test_a_us_text_asks_people_to_reply_stop(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 20:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        DB::table('crm_contacts')->where('id', $this->irishId)->update(['sms_marketing_status' => 'UNSUBSCRIBED']);
        $this->textSubscriber('sam@example.com', '+14155552671', 'US');
        $this->launch($this->smsCampaign());

        app(SmsCampaignDeliveryService::class)->deliver((int) DB::table('crm_campaign_recipients')->value('id'));

        $this->assertStringEndsWith('Reply STOP to opt out.', $this->provider->sent[0]['body']);
    }

    public function test_nothing_is_sent_at_night_and_the_dispatcher_waits_for_morning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 02:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);
        (new UniqueLock(app(CacheRepository::class)))->release(new DispatchCampaignJob($campaignId));

        app(CampaignDispatchService::class)->dispatch($campaignId);

        Bus::assertNotDispatched(SendCampaignSmsJob::class);
        Bus::assertDispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->delay !== null
            && Carbon::parse($job->delay)->equalTo(now()->addMinutes(CampaignDispatchService::MAX_SENDING_HOURS_WAIT_MINUTES)));
    }

    public function test_a_number_that_replied_stop_to_the_provider_is_unsubscribed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $this->launch($this->smsCampaign());
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');
        $this->provider->failWith = new SmsProviderException('Unsubscribed recipient', isPermanent: true, recipientOptedOut: true);

        app(SmsCampaignDeliveryService::class)->deliver($recipientId);

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame(['FAILED', 'PROVIDER_SUPPRESSED'], [$recipient->status, $recipient->failure_reason]);
        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $event = DB::table('crm_sms_consent_events')->where('contact_id', $this->irishId)->where('action', 'OPT_OUT')->sole();
        $this->assertSame(['CAMPAIGN', $recipientId, '+353851234567'], [$event->source_type, (int) $event->source_id, $event->phone]);
        $this->assertSame('SENT', $this->campaign((int) $recipient->campaign_id)->status);
    }

    public function test_a_number_the_provider_rejects_for_good_is_failed_without_touching_consent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $this->launch($this->smsCampaign());
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');
        $optOuts = DB::table('crm_sms_consent_events')->where('action', 'OPT_OUT')->count();
        $this->provider->failWith = new SmsProviderException('Not a mobile number', isPermanent: true);

        app(SmsCampaignDeliveryService::class)->deliver($recipientId);

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame(['FAILED', 'PROVIDER_REJECTED'], [$recipient->status, $recipient->failure_reason]);
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $this->assertSame($optOuts, DB::table('crm_sms_consent_events')->where('action', 'OPT_OUT')->count());
    }

    #[DataProvider('changesSinceTheSnapshot')]
    public function test_a_recipient_who_can_no_longer_be_texted_is_skipped_with_the_reason_and_the_campaign_finishes(string $change, string $reason): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');

        match ($change) {
            'unsubscribed' => DB::table('crm_contacts')->where('id', $this->irishId)->update(['sms_marketing_status' => 'UNSUBSCRIBED']),
            'consent withdrawn' => DB::table('crm_contacts')->where('id', $this->irishId)->update(['sms_marketing_status' => 'NOT_SUBSCRIBED']),
            'changed number' => DB::table('crm_contacts')->where('id', $this->irishId)->update(['phone' => '+353857654321']),
            'country switched off' => DB::table('crm_sms_connections')->where('organizer_id', $this->organizerId)->update(['enabled_countries' => json_encode(['US'])]),
            'contact deleted' => DB::table('crm_contacts')->where('id', $this->irishId)->delete(),
        };

        app(SmsCampaignDeliveryService::class)->deliver($recipientId);

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame([], $this->provider->sent);
        $this->assertSame(['SKIPPED', $reason], [$recipient->status, $recipient->failure_reason]);
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public static function changesSinceTheSnapshot(): array
    {
        return [
            'unsubscribed' => ['unsubscribed', 'UNSUBSCRIBED'],
            'consent no longer on record' => ['consent withdrawn', 'NO_CONSENT'],
            'changed number' => ['changed number', 'NO_PHONE'],
            'country switched off' => ['country switched off', 'COUNTRY_NOT_ENABLED'],
            'contact deleted' => ['contact deleted', 'CONTACT_DELETED'],
        ];
    }

    public function test_a_text_whose_time_has_passed_into_the_night_waits_instead_of_being_sent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $this->launch($this->smsCampaign());
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');
        Carbon::setTestNow(Carbon::parse('2026-11-18 02:00:00', 'UTC'));

        app(SmsCampaignDeliveryService::class)->deliver($recipientId);

        $this->assertSame([], $this->provider->sent);
        $this->assertSame('PENDING', DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('status'));
    }

    public function test_a_text_of_a_cancelled_campaign_is_skipped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');
        DB::table('crm_campaigns')->where('id', $campaignId)->update(['status' => 'CANCELLED']);

        app(SmsCampaignDeliveryService::class)->deliver($recipientId);

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame([], $this->provider->sent);
        $this->assertSame(['SKIPPED', 'CAMPAIGN_STOPPED'], [$recipient->status, $recipient->failure_reason]);
    }

    public function test_a_text_is_never_sent_twice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $this->launch($this->smsCampaign());
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');

        app(SmsCampaignDeliveryService::class)->deliver($recipientId);
        app(SmsCampaignDeliveryService::class)->deliver($recipientId);

        $this->assertCount(1, $this->provider->sent);
    }

    public function test_in_sending_hours_each_recipient_gets_a_job_that_sends_the_text_and_finishes_the_campaign(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');

        app(CampaignDispatchService::class)->dispatch($campaignId);

        Bus::assertDispatchedTimes(SendCampaignSmsJob::class, 1);
        Bus::assertDispatched(SendCampaignSmsJob::class, static fn (SendCampaignSmsJob $job) => $job->recipientId === $recipientId);

        (new SendCampaignSmsJob($recipientId))->handle(app(SmsCampaignDeliveryService::class));

        $this->assertSame(['+353851234567'], array_column($this->provider->sent, 'to'));
        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame(['SENT', 'SM1'], [$recipient->status, $recipient->provider_message_id]);
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_a_text_job_that_runs_out_of_retries_fails_its_recipient_and_lets_the_campaign_finish(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');

        (new SendCampaignSmsJob($recipientId))->failed(new SmsProviderException('Too many requests'));

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame(['FAILED', 'PROVIDER_UNAVAILABLE'], [$recipient->status, $recipient->failure_reason]);
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_a_text_campaign_is_created_as_plain_text_and_keeps_its_channel(): void
    {
        $created = $this->crm('POST', 'campaigns', ['name' => 'On sale', 'channel' => 'SMS', 'subject' => 'Ignored', 'preheader' => 'Ignored', 'body' => 'Tickets & merch on sale'], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.channel', 'SMS')
            ->assertJsonPath('data.subject', null)
            ->assertJsonPath('data.body', 'Tickets & merch on sale');
        $campaignId = $created->json('data.id');
        $this->assertNull($this->campaign($campaignId)->preheader);

        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'On sale', 'channel' => 'EMAIL', 'subject' => 'Now an email', 'body' => 'Still a text'], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.channel', 'SMS')
            ->assertJsonPath('data.subject', null)
            ->assertJsonPath('data.body', 'Still a text');
    }

    public function test_the_text_subscribers_audience_can_only_be_saved_on_a_text_campaign(): void
    {
        $this->crm('POST', 'campaigns', ['name' => 'Newsletter', 'subject' => 'News', 'preset' => 'sms_subscribers'], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preset']);
        $emailId = $this->insertCampaign();
        $this->crm('PUT', "campaigns/$emailId", ['name' => 'Newsletter', 'subject' => 'News', 'preset' => 'sms_subscribers'], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preset']);
        $this->assertNull($this->campaign($emailId)->preset_segment);

        $this->crm('POST', 'campaigns', ['name' => 'On sale', 'channel' => 'SMS', 'body' => 'On sale', 'preset' => 'sms_subscribers'], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.preset', 'sms_subscribers');
    }

    public function test_an_automation_cannot_email_the_text_subscribers_audience(): void
    {
        $this->crm('POST', 'automations', ['trigger' => 'WIN_BACK', 'name' => 'Win back', 'subject' => 'We miss you', 'body' => '<p>Come back</p>', 'preset' => 'sms_subscribers'], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preset']);
        $this->assertSame(0, DB::table('crm_automations')->count());
    }

    public function test_a_text_is_stored_exactly_as_it_was_typed(): void
    {
        $typed = "Kids <12 go free.\r\nUnder 5s & babies <3 too, R&B night &amp; more  ";
        $stored = "Kids <12 go free.\nUnder 5s & babies <3 too, R&B night &amp; more";

        $campaignId = $this->crm('POST', 'campaigns', ['name' => 'On sale', 'channel' => 'SMS', 'body' => $typed], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.body', $stored)
            ->json('data.id');

        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'On sale', 'body' => $typed], $this->ownerHeaders())->assertOk()->assertJsonPath('data.body', $stored);
        $this->crm('GET', "campaigns/$campaignId", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.body', $stored);
    }

    #[DataProvider('channelsSentWithAnUpdate')]
    public function test_an_update_past_the_text_length_limit_is_rejected_instead_of_being_cut(array $channel): void
    {
        $campaignId = $this->smsCampaign();

        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'On sale', 'body' => str_repeat('a', CampaignSmsComposer::MAX_BODY_LENGTH + 1), ...$channel], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $this->assertSame('Tickets are on sale', $this->campaign($campaignId)->body);
    }

    public static function channelsSentWithAnUpdate(): array
    {
        return [
            'no channel' => [[]],
            'the email channel' => [['channel' => 'EMAIL']],
            'the text channel' => [['channel' => 'SMS']],
        ];
    }

    public function test_an_email_campaign_keeps_its_own_length_limit_when_an_update_names_the_text_channel(): void
    {
        $campaignId = $this->insertCampaign();
        $body = '<p>'.str_repeat('a', CampaignSmsComposer::MAX_BODY_LENGTH + 1).'</p>';

        $this->crm('PUT', "campaigns/$campaignId", ['name' => 'News', 'subject' => 'News', 'channel' => 'SMS', 'body' => $body], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.channel', 'EMAIL')
            ->assertJsonPath('data.body', $body);
    }

    public function test_without_the_text_message_feature_a_text_campaign_cannot_be_created_but_a_draft_can_still_be_edited(): void
    {
        $draftId = $this->smsCampaign();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, false);
        $before = DB::table('crm_campaigns')->count();

        $this->crm('POST', 'campaigns', ['name' => 'On sale', 'channel' => 'SMS', 'body' => 'Tickets are on sale'], $this->ownerHeaders())->assertForbidden();
        $this->assertSame($before, DB::table('crm_campaigns')->count());

        $this->crm('POST', 'campaigns', ['name' => 'News', 'subject' => 'News', 'body' => '<p>Hello</p>'], $this->ownerHeaders())->assertCreated();
        $this->crm('PUT', "campaigns/$draftId", ['name' => 'Renamed', 'body' => 'Tickets are on sale'], $this->ownerHeaders())->assertOk();
    }

    public function test_a_campaign_test_text_goes_to_the_given_number_as_a_subscriber_would_get_it(): void
    {
        $campaignId = $this->smsCampaign(['body' => 'Hi {{ first_name }}, tickets are on sale']);

        $this->crm('POST', "campaigns/$campaignId/test", ['phone' => '+353 85 999 8877'], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Test sent to +353859998877');

        $this->assertCount(1, $this->provider->sent);
        $this->assertSame('+353859998877', $this->provider->sent[0]['to']);
        $this->assertNull($this->provider->sent[0]['status_callback']);
        $organizer = DB::table('organizers')->where('id', $this->organizerId)->value('name');
        $this->assertStringStartsWith($organizer.': Hi '.$this->owner->first_name.', tickets are on sale', $this->provider->sent[0]['body']);
        $this->assertSame(0, DB::table('crm_campaign_recipients')->count());
        $this->assertSame('DRAFT', $this->campaign($campaignId)->status);
    }

    public function test_the_opt_out_link_in_a_test_text_belongs_to_nobody(): void
    {
        $campaignId = $this->smsCampaign();
        $tokens = app(CampaignUnsubscribeTokenService::class);

        $this->crm('POST', "campaigns/$campaignId/test", ['phone' => '+353859998877'], $this->ownerHeaders())->assertOk();

        $link = trim(substr($this->provider->sent[0]['body'], strrpos($this->provider->sent[0]['body'], ' ') + 1));
        $token = basename((string) parse_url($link, PHP_URL_PATH));
        $this->assertSame($tokens->sampleSmsUrl(), $link);
        $this->assertNull($tokens->recipientIdFrom($token));
        $this->get('/public/crm/unsubscribe/'.$token)->assertNotFound();
        $this->post('/public/crm/unsubscribe/'.$token)->assertNotFound();
        $this->assertSame(strlen($tokens->smsUrl(1234567)), strlen($link));
    }

    public function test_a_campaign_test_text_to_a_reply_country_asks_for_stop_instead_of_a_link(): void
    {
        $campaignId = $this->smsCampaign();

        $this->crm('POST', "campaigns/$campaignId/test", ['phone' => '+14155552671'], $this->ownerHeaders())->assertOk();

        $this->assertStringEndsWith('Reply STOP to opt out.', $this->provider->sent[0]['body']);
    }

    #[DataProvider('unsendableCampaignTestTexts')]
    public function test_a_campaign_test_text_that_cannot_be_sent_explains_why_and_sends_nothing(string $case, array $request, string $reason): void
    {
        $campaignId = $this->smsCampaign($case === 'an empty message' ? ['body' => null] : []);

        match ($case) {
            'no connection' => app(SmsConnectionService::class)->disconnect($this->organizerId),
            'the provider refusing' => $this->provider->failWith = new SmsProviderException('That number can\'t receive texts.', isPermanent: true),
            'the feature being off' => $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, false),
            default => null,
        };

        $response = $this->crm('POST', "campaigns/$campaignId/test", $request, $this->ownerHeaders())->assertStatus(422);

        $this->assertStringContainsString($reason, (string) $response->getContent());
        $this->assertSame([], $this->provider->sent);
        $this->assertSame('DRAFT', $this->campaign($campaignId)->status);
    }

    public static function unsendableCampaignTestTexts(): array
    {
        return [
            'no number' => ['no number', [], 'Enter the mobile number'],
            'a landline' => ['a landline', ['phone' => '+35312345678'], 'valid mobile number'],
            'a country that is not switched on' => ['a country that is not switched on', ['phone' => '+447400123456'], 'switched on for this number'],
            'an empty message' => ['an empty message', ['phone' => '+353859998877'], 'Add a message'],
            'no connection' => ['no connection', ['phone' => '+353859998877'], 'switched on for this number'],
            'the provider refusing' => ['the provider refusing', ['phone' => '+353859998877'], 'receive texts'],
            'the feature being off' => ['the feature being off', ['phone' => '+353859998877'], 'available for this account'],
        ];
    }

    public function test_a_number_shared_by_several_contacts_is_counted_and_texted_once(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->textSubscriber('ada.work@example.com', '+353851234567', 'IE');
        $this->textSubscriber('household@example.com', '+353851234567', 'IE');
        $this->textSubscriber('grace@example.com', '+353857654321', 'IE');
        $campaignId = $this->smsCampaign();

        $this->crm('GET', "campaigns/$campaignId/send-check", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.mailable_count', 2);
        $this->launch($campaignId);

        $phones = DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->orderBy('phone')->pluck('phone')->all();
        $this->assertSame(['+353851234567', '+353857654321'], $phones);
        $this->assertSame(2, (int) $this->campaign($campaignId)->recipients_count);
    }

    public function test_the_contact_seen_most_recently_receives_the_text_for_a_shared_number(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $recentId = $this->textSubscriber('recent@example.com', '+353851234567', 'IE');
        DB::table('crm_contacts')->where('id', $this->irishId)->update(['last_activity_at' => now()->subYear()]);
        DB::table('crm_contacts')->where('id', $recentId)->update(['last_activity_at' => now()]);
        $campaignId = $this->smsCampaign();

        $this->launch($campaignId);

        $this->assertSame([$recentId], DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->pluck('contact_id')->all());
    }

    public function test_a_campaign_being_sent_says_which_countries_are_waiting_for_sending_hours_and_until_when(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 02:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $this->textSubscriber('grace@example.com', '+12025550101', 'US');
        $this->textSubscriber('alan@example.com', '+12025550102', 'US');
        $campaignId = $this->smsCampaign();
        $waiting = fn (): array => $this->crm('GET', "campaigns/$campaignId", [], $this->ownerHeaders())->assertOk()->json('data.sms_waiting');

        $this->assertSame([], $waiting());
        $this->launch($campaignId);

        $this->assertSame([
            ['country' => 'IE', 'pending_count' => 1, 'next_sending_at' => SmsCountry::IE->nextSendingTime(now())->toIso8601String()],
            ['country' => 'US', 'pending_count' => 2, 'next_sending_at' => SmsCountry::US->nextSendingTime(now())->toIso8601String()],
        ], $waiting());
        $this->assertSame($waiting(), collect($this->crm('GET', 'campaigns', [], $this->ownerHeaders())->assertOk()->json('data'))->firstWhere('id', $campaignId)['sms_waiting']);

        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        $this->assertSame(['US'], array_column($waiting(), 'country'));

        DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->where('phone_country', 'US')->update(['status' => 'SENT']);
        $this->assertSame([], $waiting());
    }

    public function test_the_recipient_list_of_a_text_campaign_shows_each_number_masked(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class]);
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);

        $response = $this->crm('GET', "campaigns/$campaignId/recipients", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.0.phone', '+35•••••••567');
        $this->assertStringNotContainsString('851234', (string) $response->getContent());

        $emailCampaignId = $this->insertCampaign(['status' => 'SENT']);
        $this->insertRecipient($emailCampaignId, $this->irishId, 'ada@example.com', ['status' => 'SENT']);
        $this->crm('GET', "campaigns/$emailCampaignId/recipients", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.0.phone', null);
    }

    public function test_an_audience_count_says_how_many_opted_in_numbers_are_in_a_country_the_connection_sends_to(): void
    {
        $this->textSubscriber('aussie@example.com', '+61412345678', 'AU');
        $this->textSubscriber('shared@example.com', '+353851234567', 'IE');
        $count = fn (): array => $this->crm('GET', 'segments/count?preset=sms_subscribers', [], $this->ownerHeaders())->assertOk()->json('data');

        $this->assertSame([3, 2, 1], [$count()['contacts_count'], $count()['textable_count'], $count()['textable_in_enabled_countries_count']]);

        app(SmsConnectionService::class)->save($this->accountId, $this->organizerId, 'AC'.str_repeat('1', 32), null, self::SERVICE_SID, ['IE', 'AU']);
        $this->assertSame(2, $count()['textable_in_enabled_countries_count']);

        app(SmsConnectionService::class)->disconnect($this->organizerId);
        $this->assertSame([2, 0], [$count()['textable_count'], $count()['textable_in_enabled_countries_count']]);
    }

    public function test_an_approved_scheduled_text_is_not_released_once_the_text_message_feature_is_off(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        Mail::fake();
        $campaignId = $this->smsCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute(), 'approved_at' => now()->subDay(), 'recipients_count' => 1]);
        DB::table('crm_campaign_recipients')->insert(['campaign_id' => $campaignId, 'organizer_id' => $this->organizerId, 'contact_id' => $this->irishId, 'email' => 'ada@example.com', 'phone' => '+353851234567', 'phone_country' => 'IE', 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, false);

        app(CampaignLaunchService::class)->launchScheduled($campaignId, now());

        $this->assertSame(['DRAFT', 'SCHEDULED_SEND_FAILED'], [$this->campaign($campaignId)->status, $this->campaign($campaignId)->status_reason]);
        $this->assertSame(0, DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->count());
        Bus::assertNotDispatched(DispatchCampaignJob::class);
        Mail::assertQueued(CampaignNotSentMail::class);
    }

    public function test_waiting_for_sending_hours_queues_one_wake_up_however_often_the_dispatcher_runs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 02:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);

        foreach (range(1, 3) as $ignored) {
            (new UniqueLock(app(CacheRepository::class)))->release(new DispatchCampaignJob($campaignId));
            app(CampaignDispatchService::class)->dispatch($campaignId);
        }

        Bus::assertDispatchedTimes(static fn (DispatchCampaignJob $job) => $job->delay !== null, 1);
    }

    public function test_the_dispatcher_keeps_waking_until_sending_hours_open_and_then_sends(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 02:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);
        $wakeUps = [];

        while (now()->lessThan(Carbon::parse('2026-11-17 09:00:00', 'UTC'))) {
            (new UniqueLock(app(CacheRepository::class)))->release(new DispatchCampaignJob($campaignId));
            app(CampaignDispatchService::class)->dispatch($campaignId);
            $wakeUp = Carbon::parse(Bus::dispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->delay !== null)->last()->delay);
            $this->assertTrue($wakeUp->greaterThan(now()));
            $this->assertTrue($wakeUp->lessThanOrEqualTo(now()->addMinutes(CampaignDispatchService::MAX_SENDING_HOURS_WAIT_MINUTES)));
            $wakeUps[] = $wakeUp->toDateTimeString();
            Carbon::setTestNow($wakeUp);
        }

        $this->assertSame('2026-11-17 09:00:00', end($wakeUps));
        $this->assertSame($wakeUps, array_values(array_unique($wakeUps)));
        Bus::assertNotDispatched(SendCampaignSmsJob::class);

        (new UniqueLock(app(CacheRepository::class)))->release(new DispatchCampaignJob($campaignId));
        app(CampaignDispatchService::class)->dispatch($campaignId);

        Bus::assertDispatchedTimes(SendCampaignSmsJob::class, 1);
    }

    #[DataProvider('recipientOrders')]
    public function test_a_campaign_to_two_countries_texts_each_only_in_its_own_daytime(bool $americanFirst): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $americanId = $this->textSubscriber('sam@example.com', '+14155552671', 'US');
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);
        $irishRecipientId = (int) DB::table('crm_campaign_recipients')->where('contact_id', $this->irishId)->value('id');
        $americanRecipientId = (int) DB::table('crm_campaign_recipients')->where('contact_id', $americanId)->value('id');

        if ($americanFirst !== ($americanRecipientId < $irishRecipientId)) {
            DB::table('crm_campaign_recipients')->where('id', $irishRecipientId)->update(['contact_id' => $americanId, 'email' => 'swap@example.com', 'phone' => '+14155552671', 'phone_country' => 'US']);
            DB::table('crm_campaign_recipients')->where('id', $americanRecipientId)->update(['contact_id' => $this->irishId, 'email' => 'ada@example.com', 'phone' => '+353851234567', 'phone_country' => 'IE']);
            DB::table('crm_campaign_recipients')->where('id', $irishRecipientId)->update(['email' => 'sam@example.com']);
            [$irishRecipientId, $americanRecipientId] = [$americanRecipientId, $irishRecipientId];
        }

        $this->runDispatcher($campaignId);

        Bus::assertDispatchedTimes(SendCampaignSmsJob::class, 1);
        Bus::assertDispatched(SendCampaignSmsJob::class, static fn (SendCampaignSmsJob $job) => $job->recipientId === $irishRecipientId);
        app(SmsCampaignDeliveryService::class)->deliver($irishRecipientId);
        app(SmsCampaignDeliveryService::class)->deliver($americanRecipientId);
        $this->assertSame(['+353851234567'], array_column($this->provider->sent, 'to'));
        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        $this->assertSame('PENDING', DB::table('crm_campaign_recipients')->where('id', $americanRecipientId)->value('status'));

        Carbon::setTestNow(now()->addSeconds(CampaignDispatchService::WAVE_DELAY_SECONDS));
        $this->runDispatcher($campaignId);
        $expectedWakeUp = now()->addMinutes(CampaignDispatchService::MAX_SENDING_HOURS_WAIT_MINUTES);
        Carbon::setTestNow(now()->addSeconds(CampaignDispatchService::WAVE_DELAY_SECONDS));
        $this->runDispatcher($campaignId);

        Bus::assertDispatchedTimes(SendCampaignSmsJob::class, 1);
        $wakeUps = Bus::dispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->delay !== null && Carbon::parse($job->delay)->greaterThan(now()->addSeconds(CampaignDispatchService::WAVE_DELAY_SECONDS)));
        $this->assertCount(1, $wakeUps);
        $this->assertTrue(Carbon::parse($wakeUps->first()->delay)->equalTo($expectedWakeUp));

        Carbon::setTestNow(SmsCountry::US->nextSendingTime(now()));
        $this->runDispatcher($campaignId);

        Bus::assertDispatched(SendCampaignSmsJob::class, static fn (SendCampaignSmsJob $job) => $job->recipientId === $americanRecipientId);
        app(SmsCampaignDeliveryService::class)->deliver($americanRecipientId);
        $this->assertSame(['+353851234567', '+14155552671'], array_column($this->provider->sent, 'to'));
        $this->assertSame('SENT', $this->campaign($campaignId)->status);
    }

    public function test_a_country_whose_sending_hours_open_mid_send_is_texted_without_waiting_for_the_rest(): void
    {
        Carbon::setTestNow(SmsCountry::US->nextSendingTime(Carbon::parse('2026-11-17 12:00:00', 'UTC'))->subMinutes(5));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $americanId = $this->textSubscriber('sam@example.com', '+14155552671', 'US');
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);
        $americanRecipientId = (int) DB::table('crm_campaign_recipients')->where('contact_id', $americanId)->value('id');
        $irishRecipientId = (int) DB::table('crm_campaign_recipients')->where('contact_id', $this->irishId)->value('id');
        $this->assertLessThan($irishRecipientId, $americanRecipientId);

        $this->runDispatcher($campaignId);
        Bus::assertNotDispatched(SendCampaignSmsJob::class, static fn (SendCampaignSmsJob $job) => $job->recipientId === $americanRecipientId);

        Carbon::setTestNow(now()->addMinutes(5));
        $this->runDispatcher($campaignId);

        Bus::assertDispatched(SendCampaignSmsJob::class, static fn (SendCampaignSmsJob $job) => $job->recipientId === $americanRecipientId);
    }

    public static function recipientOrders(): array
    {
        return [
            'the country that is asleep comes first' => [true],
            'the country that is awake comes first' => [false],
        ];
    }

    #[DataProvider('twilioOptOutDecisions')]
    public function test_twilios_own_opt_out_decision_wins_over_the_words_in_the_reply(string $startingStatus, string $body, string $optOutType, string $expectedStatus): void
    {
        $publicId = $this->useRealSignatures();
        DB::table('crm_contacts')->where('id', $this->irishId)->update(['sms_marketing_status' => $startingStatus]);

        if ($startingStatus === 'UNSUBSCRIBED') {
            $this->reply($publicId, '+353851234567', 'STOP')->assertOk();
        }

        $this->reply($publicId, '+353851234567', $body, ['OptOutType' => $optOutType])->assertOk();

        $this->assertSame($expectedStatus, $this->contact('ada@example.com')->sms_marketing_status);
    }

    public static function twilioOptOutDecisions(): array
    {
        return [
            'a keyword only Twilio knows opts out' => ['SUBSCRIBED', 'ARRET', 'STOP', 'UNSUBSCRIBED'],
            'a keyword only Twilio knows opts back in' => ['UNSUBSCRIBED', 'OUI', 'START', 'SUBSCRIBED'],
            'a help request changes nothing even if it says stop' => ['SUBSCRIBED', 'STOP', 'HELP', 'SUBSCRIBED'],
        ];
    }

    public function test_the_public_opt_out_page_for_a_text_masks_the_number_and_changes_nothing(): void
    {
        $token = $this->sentTextToken();
        $before = DB::table('crm_sms_consent_events')->count();

        $this->getJson('/public/crm/unsubscribe/'.$token)
            ->assertOk()
            ->assertExactJson(['data' => [
                'organizer_name' => DB::table('organizers')->where('id', $this->organizerId)->value('name'),
                'email' => EmailMask::mask('ada@example.com'),
                'is_unsubscribed' => false,
                'can_resubscribe' => false,
                'channel' => 'SMS',
                'phone' => '+35•••••••567',
            ]]);

        $this->assertSame($before, DB::table('crm_sms_consent_events')->count());
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
    }

    public function test_posting_a_text_opt_out_link_unsubscribes_texts_only_and_is_idempotent(): void
    {
        DB::table('orders')->where('email', 'ada@example.com')->update(['opted_into_marketing_at' => now()->subDays(5)]);
        $this->projectOrders(...DB::table('orders')->where('email', 'ada@example.com')->pluck('id')->all());
        $token = $this->sentTextToken();

        $this->postJson('/public/crm/unsubscribe/'.$token)->assertOk()->assertJsonPath('data.is_unsubscribed', true)->assertJsonPath('data.can_resubscribe', false);
        $this->postJson('/public/crm/unsubscribe/'.$token)->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $contact = $this->contact('ada@example.com');
        $this->assertSame('UNSUBSCRIBED', $contact->sms_marketing_status);
        $this->assertSame('SUBSCRIBED', $contact->email_marketing_status);
        $this->assertSame(1, DB::table('crm_sms_consent_events')->where('contact_id', $this->irishId)->where('action', 'OPT_OUT')->count());
        $this->assertSame(0, DB::table('crm_consent_events')->where('contact_id', $this->irishId)->where('action', 'OPT_OUT')->count());
    }

    public function test_a_text_opt_out_link_can_never_opt_anyone_in(): void
    {
        $token = $this->sentTextToken();
        $this->postJson('/public/crm/unsubscribe/'.$token)->assertOk();
        $emailEvents = DB::table('crm_consent_events')->count();
        $textEvents = DB::table('crm_sms_consent_events')->count();

        $this->postJson('/public/crm/unsubscribe/'.$token.'/resubscribe')->assertStatus(409);

        $this->assertSame($emailEvents, DB::table('crm_consent_events')->count());
        $this->assertSame($textEvents, DB::table('crm_sms_consent_events')->count());
        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
    }

    public function test_deleting_a_contact_removes_their_number_from_the_texts_they_were_sent(): void
    {
        $publicId = $this->useRealSignatures();
        $recipientId = $this->sentText('SM123');
        $token = app(CampaignUnsubscribeTokenService::class)->shortTokenFor($recipientId);

        app(DeleteContactHandler::class)->handle($this->organizerId, $this->irishId);

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame([null, null, 'IE'], [$recipient->contact_id, $recipient->phone, $recipient->phone_country]);
        $this->getJson('/public/crm/unsubscribe/'.$token)
            ->assertOk()
            ->assertJsonPath('data.channel', 'SMS')
            ->assertJsonPath('data.phone', null);

        $this->deliveryStatus($publicId, 'SM123', 'delivered')->assertOk();

        $this->assertNotNull(DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('delivered_at'));
    }

    public function test_a_text_opt_out_link_of_a_deleted_contact_offers_to_opt_out_without_showing_a_number_and_looking_changes_nothing(): void
    {
        $token = $this->sentTextToken();
        app(DeleteContactHandler::class)->handle($this->organizerId, $this->irishId);
        $before = $this->crmState();

        $this->getJson('/public/crm/unsubscribe/'.$token)
            ->assertOk()
            ->assertExactJson(['data' => ['organizer_name' => 'Test Organizer', 'email' => null, 'is_unsubscribed' => false, 'can_resubscribe' => false, 'channel' => 'SMS', 'phone' => null]]);

        $this->assertSame($before, $this->crmState());
    }

    public function test_confirming_a_text_opt_out_link_of_a_deleted_contact_opts_them_out_and_creates_nothing(): void
    {
        $token = $this->sentTextToken();
        app(DeleteContactHandler::class)->handle($this->organizerId, $this->irishId);
        $before = DB::table('crm_sms_consent_events')->count();

        $this->postJson('/public/crm/unsubscribe/'.$token)->assertOk()->assertJsonPath('data.is_unsubscribed', true)->assertJsonPath('data.channel', 'SMS');
        $this->getJson('/public/crm/unsubscribe/'.$token)->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $this->assertSame($before, DB::table('crm_sms_consent_events')->count());
        $this->assertNull($this->contact('ada@example.com'));
    }

    public function test_a_text_opt_out_link_of_a_contact_deleted_after_opting_out_of_texts_says_they_have_opted_out(): void
    {
        $token = $this->sentTextToken();
        $this->postJson('/public/crm/unsubscribe/'.$token)->assertOk();
        app(DeleteContactHandler::class)->handle($this->organizerId, $this->irishId);

        $this->getJson('/public/crm/unsubscribe/'.$token)->assertOk()->assertJsonPath('data.is_unsubscribed', true)->assertJsonPath('data.phone', null);
    }

    public function test_opting_out_from_the_link_in_a_text_after_the_contact_was_deleted_keeps_them_opted_out_when_they_are_added_again(): void
    {
        $token = $this->sentTextToken();
        app(DeleteContactHandler::class)->handle($this->organizerId, $this->irishId);

        $this->postJson('/public/crm/unsubscribe/'.$token)->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $restored = $this->restoreDeletedContact('ada@example.com');
        $this->assertSame('+353851234567', $restored->getPhone());
        $this->assertSame('UNSUBSCRIBED', $restored->getSmsMarketingStatus());
    }

    public function test_an_old_text_opt_out_link_still_works_for_a_contact_who_was_deleted_and_added_again(): void
    {
        $token = $this->sentTextToken();
        app(DeleteContactHandler::class)->handle($this->organizerId, $this->irishId);
        $restored = $this->restoreDeletedContact('ada@example.com');
        $this->assertSame('SUBSCRIBED', $restored->getSmsMarketingStatus());

        $this->postJson('/public/crm/unsubscribe/'.$token)->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $this->assertSame('UNSUBSCRIBED', DB::table('crm_contacts')->where('id', $restored->getId())->value('sms_marketing_status'));
    }

    public function test_a_draft_preview_shows_each_text_the_enabled_countries_would_get_and_how_many_parts_it_bills(): void
    {
        $preview = app(PreviewSmsCampaignDraftHandler::class)->handle(new PreviewSmsCampaignDraftDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            body: 'Hi {{ first_name | default: "there" }}, doors open at 7 tonight. See you at the bar.',
            event_id: null,
            early_access_product_ids: [],
            campaign_id: null,
            can_view_financials: true,
            viewer: new CampaignRecipientDTO(first_name: 'Grace', last_name: 'Hopper'),
        ))->toArray();

        $organizer = DB::table('organizers')->where('id', $this->organizerId)->value('name');
        $message = $organizer.': Hi Grace, doors open at 7 tonight. See you at the bar.';
        $withStop = $message."\nReply STOP to opt out.";
        $withLink = $message."\nOpt out: ".app(CampaignUnsubscribeTokenService::class)->sampleSmsUrl();
        $this->assertSame([
            'variants' => [
                ['countries' => ['IE'], 'text' => $withLink, 'characters' => strlen($withLink), 'parts' => 1, 'is_unicode' => false],
                ['countries' => ['US'], 'text' => $withStop, 'characters' => strlen($withStop), 'parts' => 1, 'is_unicode' => false],
            ],
            'uses_placeholders' => true,
            'estimate' => null,
        ], $preview);
    }

    public function test_a_saved_text_can_be_previewed_filled_in_by_someone_who_can_only_read_and_without_a_licence(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        DB::table('users')->where('id', $viewer->id)->update(['first_name' => 'Grace', 'last_name' => 'Hopper']);
        $campaignId = $this->smsCampaign(['status' => 'SENT', 'body' => 'Hi {{ first_name | default: "there" }}, doors open at 7.']);
        $organizer = DB::table('organizers')->where('id', $this->organizerId)->value('name');
        $message = $organizer.': Hi Grace, doors open at 7.';

        $this->useLicenceKey(null);
        $preview = $this->crm('GET', "campaigns/$campaignId/preview", [], $this->memberHeaders($viewer))->assertOk()->json('data');

        $this->assertSame([
            ['countries' => ['IE'], 'text' => $message."\nOpt out: ".app(CampaignUnsubscribeTokenService::class)->sampleSmsUrl()],
            ['countries' => ['US'], 'text' => $message."\nReply STOP to opt out."],
        ], array_map(static fn (array $variant) => ['countries' => $variant['countries'], 'text' => $variant['text']], $preview['variants']));
        $this->assertTrue($preview['uses_placeholders']);
        $this->assertNull($preview['estimate']);
        $this->assertArrayNotHasKey('html', $preview);
    }

    #[DataProvider('connectionsToPreviewFor')]
    public function test_a_draft_preview_only_shows_the_opt_out_lines_the_connection_uses(?array $enabledCountries, array $expectedCountries): void
    {
        $enabledCountries === null
            ? app(SmsConnectionService::class)->disconnect($this->organizerId)
            : app(SmsConnectionService::class)->save($this->accountId, $this->organizerId, 'AC'.str_repeat('1', 32), null, self::SERVICE_SID, $enabledCountries);

        $preview = app(PreviewSmsCampaignDraftHandler::class)->handle(new PreviewSmsCampaignDraftDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            body: 'Tickets are on sale 🎉',
            event_id: null,
            early_access_product_ids: [],
            campaign_id: null,
            can_view_financials: true,
            viewer: new CampaignRecipientDTO(first_name: 'Grace', last_name: 'Hopper'),
        ));

        $this->assertSame($expectedCountries, array_map(static fn (SmsPreviewVariantDTO $variant) => $variant->countries, $preview->variants));
        $this->assertFalse($preview->uses_placeholders);
        $this->assertTrue($preview->variants[0]->is_unicode);
    }

    public static function connectionsToPreviewFor(): array
    {
        return [
            'only a reply country' => [['US'], [['US']]],
            'only link countries' => [['AU', 'GB'], [['GB', 'AU']]],
            'no connection yet' => [null, [['GB', 'IE', 'AU'], ['US']]],
        ];
    }

    public function test_the_estimate_counts_each_number_once_per_enabled_country_and_the_parts_it_will_bill(): void
    {
        $this->textSubscriber('ada.work@example.com', '+353851234567', 'IE');
        $this->textSubscriber('grace@example.com', '+353857654321', 'IE');
        $this->textSubscriber('sam@example.com', '+14155552671', 'US');
        $this->textSubscriber('aussie@example.com', '+61412345678', 'AU');
        $campaignId = $this->smsCampaign(['body' => str_repeat('a', 150)]);

        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));

        $preview = $this->previewFor($campaignId, str_repeat('a', 150), canViewFinancials: true)->toArray();

        $this->assertSame([['IE'], ['US']], array_column($preview['variants'], 'countries'));
        $this->assertSame([2, 2], array_column($preview['variants'], 'parts'));
        $this->assertSame([
            'countries' => [
                ['country' => 'IE', 'recipients' => 2, 'parts' => 2, 'is_sending_now' => true, 'next_sending_at' => now()->toIso8601String()],
                ['country' => 'US', 'recipients' => 1, 'parts' => 2, 'is_sending_now' => false, 'next_sending_at' => SmsCountry::US->nextSendingTime(now())->toIso8601String()],
            ],
            'recipients' => 3,
            'message_parts' => 6,
        ], $preview['estimate']);
    }

    public function test_the_estimate_uses_the_text_being_previewed_and_the_saved_audience(): void
    {
        $campaignId = $this->smsCampaign(['body' => str_repeat('a', 150)]);

        $estimate = $this->previewFor($campaignId, 'Short', canViewFinancials: true)->estimate;

        $this->assertSame([1, 1], [$estimate->recipients, $estimate->message_parts]);
    }

    public function test_an_audience_filtered_by_spend_is_not_counted_for_someone_who_cannot_view_financials(): void
    {
        $campaignId = $this->smsCampaign(['segment_definition' => json_encode(['match' => 'all', 'rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 1]]])]);

        $this->assertNull($this->previewFor($campaignId, 'Short', canViewFinancials: false)->estimate);
        $this->assertNotNull($this->previewFor($campaignId, 'Short', canViewFinancials: true)->estimate);
    }

    public function test_a_draft_text_preview_accepts_an_empty_draft_and_rejects_oversized_or_malformed_input(): void
    {
        $this->crm('POST', 'campaigns/sms-preview', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonStructure(['data' => ['variants' => [['countries', 'text', 'characters', 'parts', 'is_unicode']], 'uses_placeholders']])
            ->assertJsonPath('data.estimate', null);
        $this->crm('POST', 'campaigns/sms-preview', ['body' => null, 'event_id' => null, 'campaign_id' => null], $this->ownerHeaders())->assertOk();
        $this->crm('POST', 'campaigns/sms-preview', ['body' => str_repeat('a', CampaignSmsComposer::MAX_BODY_LENGTH)], $this->ownerHeaders())->assertOk();

        $invalid = [
            'body' => [str_repeat('a', CampaignSmsComposer::MAX_BODY_LENGTH + 1), ['Hello']],
            'event_id' => ['abc', 0],
            'campaign_id' => ['abc', 0],
            'early_access_product_ids' => ['1', range(1, 51)],
            'early_access_product_ids.0' => [['abc'], [0]],
        ];

        foreach ($invalid as $field => $values) {
            foreach ($values as $value) {
                $this->crm('POST', 'campaigns/sms-preview', [explode('.', $field)[0] => $value], $this->ownerHeaders())
                    ->assertStatus(422)
                    ->assertJsonValidationErrors([$field]);
            }
        }
    }

    public function test_a_draft_text_preview_only_counts_recipients_for_a_member_who_may_see_the_audience(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spendCampaignId = $this->smsCampaign(['segment_definition' => json_encode(['match' => 'all', 'rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 1]]])]);
        $everyoneCampaignId = $this->smsCampaign();
        $body = ['body' => 'Short'];

        $this->crm('POST', 'campaigns/sms-preview', [...$body, 'campaign_id' => $spendCampaignId], $this->memberHeaders($member))
            ->assertOk()
            ->assertJsonPath('data.estimate', null)
            ->assertJsonCount(2, 'data.variants');
        $this->crm('POST', 'campaigns/sms-preview', [...$body, 'campaign_id' => $spendCampaignId], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.estimate.recipients', 0);
        $this->crm('POST', 'campaigns/sms-preview', [...$body, 'campaign_id' => $everyoneCampaignId], $this->memberHeaders($member))
            ->assertOk()
            ->assertJsonPath('data.estimate.recipients', 1);
    }

    public function test_a_draft_text_preview_never_reads_another_organizers_campaign_or_event(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $otherCampaignId = $this->insertCampaign(['channel' => 'SMS', 'subject' => null, 'body' => 'Elsewhere'], $otherOrganizerId);
        $otherEventId = $this->createEvent($this->accountId, $otherOrganizerId, 'Other Event');
        $body = ['body' => 'See you at {{ event_title | default: "the show" }}'];
        $texts = static fn (TestResponse $response) => array_column($response->assertOk()->json('data.variants'), 'text');

        $this->crm('POST', 'campaigns/sms-preview', [...$body, 'campaign_id' => $otherCampaignId], $this->ownerHeaders())->assertNotFound();
        $this->crm('POST', 'campaigns/sms-preview', [...$body, 'campaign_id' => 999999999], $this->ownerHeaders())->assertNotFound();
        $this->crm('POST', 'campaigns/sms-preview', [...$body, 'campaign_id' => $this->insertCampaign()], $this->ownerHeaders())->assertNotFound();

        $withoutEvent = $texts($this->crm('POST', 'campaigns/sms-preview', $body, $this->ownerHeaders()));
        $this->assertStringContainsString('See you at the show', $withoutEvent[0]);
        $this->assertSame($withoutEvent, $texts($this->crm('POST', 'campaigns/sms-preview', [...$body, 'event_id' => $otherEventId], $this->ownerHeaders())));
        $this->assertStringContainsString('See you at Main Event', $texts($this->crm('POST', 'campaigns/sms-preview', [...$body, 'event_id' => $this->eventId], $this->ownerHeaders()))[0]);
    }

    public function test_an_email_campaign_has_no_text_estimate(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->previewFor($this->insertCampaign(), 'Short', canViewFinancials: true);
    }

    public function test_the_review_queue_previews_a_text_with_the_opt_out_line_its_recipients_will_get(): void
    {
        app(SmsConnectionService::class)->save($this->accountId, $this->organizerId, 'AC'.str_repeat('1', 32), null, self::SERVICE_SID, ['US']);
        $pendingId = $this->smsCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN', 'body' => 'Hi {{ first_name | default: "there" }}, tickets are on sale']);

        $listed = collect($this->json('GET', '/admin/crm/campaigns/pending-review', [], $this->memberHeaders($this->addAccountUser($this->accountId, Role::SUPERADMIN)))->assertOk()->json('data'))->keyBy('id');

        $organizer = DB::table('organizers')->where('id', $this->organizerId)->value('name');
        $this->assertSame($organizer.": Hi there, tickets are on sale\nReply STOP to opt out.", $listed[$pendingId]['preview_text']);
        $this->assertNull($listed[$pendingId]['preview_html']);
    }

    #[DataProvider('invalidTextCampaigns')]
    public function test_an_invalid_text_campaign_is_rejected(array $overrides, string $field): void
    {
        $before = DB::table('crm_campaigns')->count();

        $this->crm('POST', 'campaigns', ['name' => 'On sale', 'channel' => 'SMS', 'body' => 'Tickets are on sale', ...$overrides], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);

        $this->assertSame($before, DB::table('crm_campaigns')->count());
    }

    public static function invalidTextCampaigns(): array
    {
        return [
            'a channel that does not exist' => [['channel' => 'PIGEON'], 'channel'],
            'a message longer than a text campaign allows' => [['body' => str_repeat('a', CampaignSmsComposer::MAX_BODY_LENGTH + 1)], 'body'],
            'a placeholder that does not exist' => [['body' => 'Hi {{ nickname }}'], 'body'],
            'an event placeholder without an event' => [['body' => 'Book at {{ event_url }}'], 'body'],
        ];
    }

    public function test_a_message_at_the_text_length_limit_is_accepted(): void
    {
        $body = str_repeat('a', CampaignSmsComposer::MAX_BODY_LENGTH);

        $this->crm('POST', 'campaigns', ['name' => 'On sale', 'channel' => 'SMS', 'body' => $body], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.body', $body);
    }

    public function test_sending_a_text_campaign_snapshots_text_subscribers_and_starts_sending(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        $this->insertSubscribedContact('emailonly@example.com');
        $campaignId = $this->smsCampaign();

        $this->crm('GET', "campaigns/$campaignId/send-check", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.mailable_count', 1)
            ->assertJsonPath('data.blocked_reason', null);
        $this->crm('POST', "campaigns/$campaignId/send", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'SENDING');

        $this->assertSame(['+353851234567'], DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->pluck('phone')->all());
        Bus::assertDispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->campaignId === $campaignId);
    }

    public function test_a_text_campaign_nobody_can_receive_is_not_sent(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        DB::table('crm_contacts')->where('id', $this->irishId)->update(['sms_marketing_status' => 'UNSUBSCRIBED']);
        $this->insertSubscribedContact('emailonly@example.com');
        $campaignId = $this->smsCampaign();

        $this->crm('POST', "campaigns/$campaignId/send", [], $this->ownerHeaders())->assertStatus(422);

        $this->assertSame('DRAFT', $this->campaign($campaignId)->status);
        $this->assertSame(0, DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->count());
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_in_saas_mode_a_first_text_campaign_waits_for_review_and_is_sent_once_approved(): void
    {
        Bus::fake([DispatchCampaignJob::class, NotifyCampaignPendingReviewJob::class]);
        config(['app.saas_mode_enabled' => true]);
        $campaignId = $this->smsCampaign();

        $this->crm('POST', "campaigns/$campaignId/send", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING_REVIEW')
            ->assertJsonPath('data.status_reason', 'FIRST_CAMPAIGN');

        Bus::assertNotDispatched(DispatchCampaignJob::class);
        Bus::assertDispatched(NotifyCampaignPendingReviewJob::class);
        $this->assertSame([['+353851234567', 'PENDING']], DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->get()->map(static fn (object $row) => [$row->phone, $row->status])->all());
        $this->assertSame([], $this->provider->sent);

        $this->json('POST', "/admin/crm/campaigns/$campaignId/approve", ['reviewed_version' => $this->campaign($campaignId)->updated_at], $this->headersFor($this->addAccountUser($this->accountId, Role::SUPERADMIN), $this->accountId))
            ->assertOk();

        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        Bus::assertDispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->campaignId === $campaignId);
    }

    public function test_a_text_offering_early_access_must_carry_the_event_link(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $campaignId = $this->smsCampaign(['event_id' => $this->eventId, 'early_access_product_ids' => json_encode([$this->productId])]);

        $this->assertNotSendable($campaignId, 'Add the event link to your message');
        $this->crm('GET', "campaigns/$campaignId/send-check", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.blocked_reason', 'Add the event link to your message so people can reach the early access tickets.');
        $this->assertSame('DRAFT', $this->campaign($campaignId)->status);
        $this->assertSame(0, DB::table('promo_codes')->where('event_id', $this->eventId)->count());
        $this->assertFalse((bool) DB::table('products')->where('id', $this->productId)->value('is_hidden_without_promo_code'));

        DB::table('crm_campaigns')->where('id', $campaignId)->update(['body' => 'Early tickets: {{ event_url }}']);
        $this->launch($campaignId);

        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
        $this->assertNotNull($this->campaign($campaignId)->promo_code_id);
    }

    public function test_a_text_linked_to_an_event_without_early_access_does_not_need_the_event_link(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $campaignId = $this->smsCampaign(['event_id' => $this->eventId, 'early_access_product_ids' => null]);

        $this->launch($campaignId);

        $this->assertSame('SENDING', $this->campaign($campaignId)->status);
    }

    public function test_the_recipient_list_shows_a_text_as_delivered_from_its_delivery_receipt(): void
    {
        $campaignId = $this->smsCampaign(['status' => 'SENT']);
        $deliveredAt = now()->subMinutes(5)->startOfSecond();
        $deliveredId = $this->insertRecipient($campaignId, $this->irishId, 'ada@example.com', ['phone' => '+353851234567', 'phone_country' => 'IE', 'status' => 'SENT', 'provider_message_id' => 'SM1', 'delivered_at' => $deliveredAt]);
        $unconfirmedId = $this->insertRecipient($campaignId, $this->insertContact('sam@example.com'), 'sam@example.com', ['phone' => '+353852222222', 'phone_country' => 'IE', 'status' => 'SENT', 'provider_message_id' => 'SM2']);

        $all = collect($this->crm('GET', "campaigns/$campaignId/recipients", [], $this->ownerHeaders())->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($deliveredAt->equalTo(Carbon::parse($all[$deliveredId]['delivered_at'])));
        $this->assertNull($all[$unconfirmedId]['delivered_at']);

        $delivered = $this->crm('GET', "campaigns/$campaignId/recipients?filter=delivered", [], $this->ownerHeaders())->assertOk();
        $this->assertSame([$deliveredId], array_column($delivered->json('data'), 'id'));
    }

    public function test_a_send_the_provider_may_have_accepted_is_not_sent_again(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $this->launch($this->smsCampaign());
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');
        $this->provider->failWith = new SmsProviderException('Timed out', mayHaveBeenAccepted: true);

        app(SmsCampaignDeliveryService::class)->deliver($recipientId);

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame(['FAILED', 'TIMEOUT'], [$recipient->status, $recipient->failure_reason]);
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);

        $this->provider->failWith = null;
        app(SmsCampaignDeliveryService::class)->deliver($recipientId);

        $this->assertSame([], $this->provider->sent);
    }

    public function test_a_send_the_provider_refused_for_now_is_put_back_for_a_retry(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $this->launch($this->smsCampaign());
        $recipientId = (int) DB::table('crm_campaign_recipients')->value('id');
        $this->provider->failWith = new SmsProviderException('Too many requests');

        try {
            app(SmsCampaignDeliveryService::class)->deliver($recipientId);
            $this->fail('A retryable failure should reach the queue.');
        } catch (SmsProviderException) {
            $this->assertSame('PENDING', DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('status'));
        }
    }

    public function test_the_connection_test_message_names_the_organizer_and_this_installation(): void
    {
        config(['app.name' => 'Ticket Shack']);

        app(SendSmsTestHandler::class)->handle($this->organizerId, '+353851234567');

        $organizer = DB::table('organizers')->where('id', $this->organizerId)->value('name');
        $this->assertStringStartsWith($organizer.': ', $this->provider->sent[0]['body']);
        $this->assertStringContainsString('Ticket Shack', $this->provider->sent[0]['body']);
        $this->assertStringNotContainsString('Hi.Events', $this->provider->sent[0]['body']);
    }

    public function test_a_connection_test_without_a_connection_says_to_connect_twilio(): void
    {
        app(SmsConnectionService::class)->disconnect($this->organizerId);

        try {
            app(SendSmsTestHandler::class)->handle($this->organizerId, '+353851234567');
            $this->fail('A test message needs a connection.');
        } catch (SmsProviderException $exception) {
            $this->assertStringContainsString('Connect Twilio', $exception->getMessage());
            $this->assertSame([], $this->provider->sent);
        }
    }

    #[DataProvider('optOutKeywords')]
    public function test_a_signed_opt_out_reply_unsubscribes_the_number(string $body): void
    {
        $publicId = $this->useRealSignatures();

        $this->reply($publicId, '+353851234567', $body)->assertOk();

        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $event = DB::table('crm_sms_consent_events')->where('contact_id', $this->irishId)->where('action', 'OPT_OUT')->first();
        $this->assertSame('SMS_REPLY', $event->source_type);
        $this->assertSame('+353851234567', $event->phone);
    }

    public static function optOutKeywords(): array
    {
        return [
            'stop' => ['STOP'],
            'lower case with spaces' => [' stop '],
            'stopall' => ['STOPALL'],
            'unsubscribe' => ['Unsubscribe'],
            'cancel' => ['CANCEL'],
            'end' => ['END'],
            'quit' => ['QUIT'],
            'optout' => ['OPTOUT'],
            'revoke' => ['REVOKE'],
        ];
    }

    #[DataProvider('optInKeywords')]
    public function test_a_signed_opt_in_reply_resubscribes_a_number_that_had_replied_stop(string $body): void
    {
        $publicId = $this->useRealSignatures();
        $this->reply($publicId, '+353851234567', 'STOP')->assertOk();

        $this->reply($publicId, '+353851234567', $body)->assertOk();

        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
    }

    public static function optInKeywords(): array
    {
        return [
            'start' => ['Start'],
            'unstop' => ['UNSTOP'],
        ];
    }

    #[DataProvider('waysOutOtherThanAReply')]
    public function test_a_start_reply_resubscribes_a_number_however_it_was_unsubscribed(string $wayOut): void
    {
        $publicId = $this->useRealSignatures();

        match ($wayOut) {
            'staff' => app(UnsubscribeContactHandler::class)->handle($this->organizerId, $this->irishId, $this->owner->id, CrmMarketingChannel::SMS),
            'link' => app(CampaignUnsubscribeService::class)->unsubscribe($this->sentTextToken()),
        };
        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);

        $this->reply($publicId, '+353851234567', 'START')->assertOk();

        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
    }

    public static function waysOutOtherThanAReply(): array
    {
        return ['unsubscribed by staff' => ['staff'], 'opted out through the link in a text' => ['link']];
    }

    public function test_a_stop_reply_unsubscribes_every_contact_of_the_organizer_with_that_number(): void
    {
        $publicId = $this->useRealSignatures();
        $this->textSubscriber('shared@example.com', '+353851234567', 'IE');

        $this->reply($publicId, '+353851234567', 'STOP')->assertOk();

        $this->assertSame(
            ['UNSUBSCRIBED', 'UNSUBSCRIBED'],
            [$this->contact('ada@example.com')->sms_marketing_status, $this->contact('shared@example.com')->sms_marketing_status],
        );
    }

    #[DataProvider('waysOutOfTexts')]
    public function test_opting_a_number_out_stops_every_later_text_to_it_whichever_contact_holds_it(string $wayOut): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $this->textSubscriber('work@example.com', '+353851234567', 'IE');
        $this->textSubscriber('grace@example.com', '+353852222222', 'IE');
        $this->launch($this->smsCampaign());
        $texted = DB::table('crm_campaign_recipients')->where('phone', '+353851234567')->sole();
        $otherHolderId = (int) DB::table('crm_contacts')->where('phone', '+353851234567')->where('id', '<>', $texted->contact_id)->value('id');

        if ($wayOut === 'provider rejection') {
            $this->provider->failWith = new SmsProviderException('Unsubscribed recipient', isPermanent: true, recipientOptedOut: true);
        }

        app(SmsCampaignDeliveryService::class)->deliver((int) $texted->id);
        $this->provider->failWith = null;

        match ($wayOut) {
            'link' => app(CampaignUnsubscribeService::class)->unsubscribe(app(CampaignUnsubscribeTokenService::class)->shortTokenFor((int) $texted->id)),
            'staff' => app(UnsubscribeContactHandler::class)->handle($this->organizerId, $otherHolderId, $this->owner->id, CrmMarketingChannel::SMS),
            'reply' => app(SmsWebhookService::class)->recordReply(app(SmsConnectionService::class)->findForOrganizer($this->organizerId), '+353851234567', 'STOP', ''),
            'provider rejection' => null,
        };

        $this->assertSame(
            ['UNSUBSCRIBED', 'UNSUBSCRIBED'],
            [$this->contact('ada@example.com')->sms_marketing_status, $this->contact('work@example.com')->sms_marketing_status],
        );

        $nextCampaignId = $this->smsCampaign();
        $this->launch($nextCampaignId);

        $this->assertSame(['+353852222222'], DB::table('crm_campaign_recipients')->where('campaign_id', $nextCampaignId)->pluck('phone')->all());
    }

    public static function waysOutOfTexts(): array
    {
        return [
            'the link in a text' => ['link'],
            'staff opting out the other contact with the number' => ['staff'],
            'a STOP reply' => ['reply'],
            'the provider rejecting the number as opted out' => ['provider rejection'],
        ];
    }

    public function test_a_text_already_queued_for_one_contact_is_not_sent_once_another_contact_with_the_number_opts_out(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        $this->textSubscriber('work@example.com', '+353851234567', 'IE');
        $this->launch($this->smsCampaign());
        $queued = DB::table('crm_campaign_recipients')->sole();
        $otherHolderId = (int) DB::table('crm_contacts')->where('phone', '+353851234567')->where('id', '<>', $queued->contact_id)->value('id');
        $earlierText = $this->insertRecipient($this->smsCampaign(['status' => 'SENT']), $otherHolderId, 'earlier@example.com', ['phone' => '+353851234567', 'phone_country' => 'IE', 'status' => 'SENT']);

        $this->postJson('/public/crm/unsubscribe/'.app(CampaignUnsubscribeTokenService::class)->shortTokenFor($earlierText))->assertOk()->assertJsonPath('data.is_unsubscribed', true);
        app(SmsCampaignDeliveryService::class)->deliver((int) $queued->id);

        $this->assertSame([], $this->provider->sent);
        $skipped = DB::table('crm_campaign_recipients')->where('id', $queued->id)->first();
        $this->assertSame(['SKIPPED', 'UNSUBSCRIBED'], [$skipped->status, $skipped->failure_reason]);
    }

    public function test_an_old_opt_out_link_still_works_after_the_number_opted_in_again(): void
    {
        $publicId = $this->useRealSignatures();
        $token = $this->sentTextToken();
        $this->postJson('/public/crm/unsubscribe/'.$token)->assertOk()->assertJsonPath('data.is_unsubscribed', true);
        $this->travel(1)->days();
        $this->reply($publicId, '+353851234567', 'START')->assertOk();
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $this->travel(1)->days();

        $this->postJson('/public/crm/unsubscribe/'.$token)->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
    }

    #[DataProvider('connectionsLostMidSend')]
    public function test_a_campaign_whose_twilio_connection_stops_working_mid_send_is_paused_and_its_creator_told(string $loss, string $reason): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        Bus::fake([DispatchCampaignJob::class, SendCampaignSmsJob::class]);
        Mail::fake();
        $this->textSubscriber('grace@example.com', '+353852222222', 'IE');
        $campaignId = $this->smsCampaign();
        $this->launch($campaignId);
        $recipientIds = DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->orderBy('id')->pluck('id')->all();

        match ($loss) {
            'credentials refused' => $this->provider->failWith = new SmsProviderException('Authenticate', isPermanent: true, connectionRefused: true),
            'disconnected' => app(SmsConnectionService::class)->disconnect($this->organizerId),
        };

        foreach ($recipientIds as $recipientId) {
            app(SmsCampaignDeliveryService::class)->deliver((int) $recipientId);
        }

        $this->assertSame(['PAUSED', $reason], [$this->campaign($campaignId)->status, $this->campaign($campaignId)->status_reason]);
        $this->assertSame(
            [['SKIPPED', 'CAMPAIGN_STOPPED'], ['SKIPPED', 'CAMPAIGN_STOPPED']],
            DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->orderBy('id')->get()->map(static fn (object $recipient) => [$recipient->status, $recipient->failure_reason])->all(),
        );
        $this->assertSame([], $this->provider->sent);
        Mail::assertQueued(CampaignNotSentMail::class, 1);
        Mail::assertQueued(CampaignNotSentMail::class, fn (CampaignNotSentMail $mail) => $mail->hasTo($this->owner->email));
    }

    public static function connectionsLostMidSend(): array
    {
        return [
            'twilio stops accepting the saved credentials' => ['credentials refused', SmsCampaignDeliveryService::REASON_CONNECTION_REFUSED],
            'twilio is disconnected' => ['disconnected', SmsCampaignDeliveryService::REASON_DISCONNECTED],
        ];
    }

    public function test_a_text_campaign_whose_organizer_is_deleted_mid_send_is_cancelled_and_its_remaining_recipients_skipped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00', 'UTC'));
        $campaignId = $this->smsCampaign(['status' => 'SENDING']);
        $recipient = ['phone_country' => 'IE', 'status' => 'PENDING'];
        $firstId = $this->insertRecipient($campaignId, $this->irishId, 'ada@example.com', ['phone' => '+353851234567', ...$recipient]);
        $secondId = $this->insertRecipient($campaignId, $this->textSubscriber('grace@example.com', '+353852222222', 'IE'), 'grace@example.com', ['phone' => '+353852222222', ...$recipient]);
        DB::table('organizers')->where('id', $this->organizerId)->update(['deleted_at' => now()]);

        app(SmsCampaignDeliveryService::class)->deliver($firstId);
        app(SmsCampaignDeliveryService::class)->deliver($secondId);

        $this->assertSame(['CANCELLED', CampaignDispatchService::REASON_ORGANIZER_DELETED], [$this->campaign($campaignId)->status, $this->campaign($campaignId)->status_reason]);
        $this->assertSame(
            [['SKIPPED', 'CAMPAIGN_STOPPED'], ['SKIPPED', 'CAMPAIGN_STOPPED']],
            DB::table('crm_campaign_recipients')->whereIn('id', [$firstId, $secondId])->orderBy('id')->get()
                ->map(static fn (object $recipient) => [$recipient->status, $recipient->failure_reason])->all(),
        );
        $this->assertSame([], $this->provider->sent);
    }

    public function test_a_start_reply_does_not_subscribe_a_number_that_never_opted_in(): void
    {
        $publicId = $this->useRealSignatures();
        $quietId = $this->insertContact('quiet@example.com', ['phone' => '+353851111111', 'phone_country' => 'IE']);

        $this->reply($publicId, '+353851111111', 'START')->assertOk();

        $this->assertSame('NOT_SUBSCRIBED', $this->contact('quiet@example.com')->sms_marketing_status);
        $this->assertSame(0, DB::table('crm_sms_consent_events')->where('contact_id', $quietId)->count());
    }

    #[DataProvider('repliesThatChangeNothing')]
    public function test_a_reply_that_is_not_a_keyword_or_comes_from_an_unknown_number_changes_nothing(string $from, string $body): void
    {
        $publicId = $this->useRealSignatures();
        $before = DB::table('crm_sms_consent_events')->count();

        $this->reply($publicId, $from, $body)->assertOk();

        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $this->assertSame($before, DB::table('crm_sms_consent_events')->count());
    }

    public static function repliesThatChangeNothing(): array
    {
        return [
            'a sentence containing stop' => ['+353851234567', 'Please stop by on Friday'],
            'a thank you' => ['+353851234567', 'Thanks!'],
            'stop from a number nobody has' => ['+353859999999', 'STOP'],
        ];
    }

    public function test_a_reply_only_unsubscribes_the_number_for_the_organizer_it_was_sent_to(): void
    {
        $publicId = $this->useRealSignatures();
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $this->trackOrganizer($this->accountId, $otherOrganizerId);
        $this->insertContact('ada@example.com', ['phone' => '+353851234567', 'phone_country' => 'IE', 'sms_marketing_status' => 'SUBSCRIBED'], $otherOrganizerId);

        $this->reply($publicId, '+353851234567', 'STOP')->assertOk();

        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com', $otherOrganizerId)->sms_marketing_status);
    }

    #[DataProvider('badSignatures')]
    public function test_a_webhook_that_is_not_signed_for_this_connection_and_url_is_refused(string $case): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $otherPublicId = app(SmsConnectionService::class)->save($this->accountId, $otherOrganizerId, 'AC'.str_repeat('2', 32), 'another-auth-token', 'MG00000000000000000000000000000002', ['IE'])->getPublicId();
        $publicId = $this->useRealSignatures();
        $reply = ['From' => '+353851234567', 'Body' => 'STOP'];

        $headers = match ($case) {
            'no signature header' => [],
            'an empty signature' => ['X-Twilio-Signature' => ''],
            'signed for the status url' => ['X-Twilio-Signature' => $this->sign($publicId, 'status', $reply)],
            'signed with another organizers token for their url' => ['X-Twilio-Signature' => $this->sign($otherPublicId, 'inbound', $reply, 'another-auth-token')],
            'signed with another organizers token for this url' => ['X-Twilio-Signature' => $this->sign($publicId, 'inbound', $reply, 'another-auth-token')],
            'signed for different parameters' => ['X-Twilio-Signature' => $this->sign($publicId, 'inbound', ['From' => '+353851234567', 'Body' => 'START'])],
        };

        $this->post($this->webhookUrl($publicId, 'inbound'), $reply, $headers)->assertUnauthorized();

        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
    }

    public static function badSignatures(): array
    {
        return [
            'no signature header' => ['no signature header'],
            'an empty signature' => ['an empty signature'],
            'signed for the status url' => ['signed for the status url'],
            'signed with another organizers token for their url' => ['signed with another organizers token for their url'],
            'signed with another organizers token for this url' => ['signed with another organizers token for this url'],
            'signed for different parameters' => ['signed for different parameters'],
        ];
    }

    public function test_a_webhook_for_a_connection_that_does_not_exist_is_refused(): void
    {
        $publicId = $this->useRealSignatures();
        $unknownPublicId = 'smsc_'.str_repeat('z', 24);
        $reply = ['From' => '+353851234567', 'Body' => 'STOP'];

        $this->post($this->webhookUrl($unknownPublicId, 'inbound'), $reply, ['X-Twilio-Signature' => $this->sign($unknownPublicId, 'inbound', $reply)])->assertUnauthorized();

        $this->assertNotSame($unknownPublicId, $publicId);
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->sms_marketing_status);
    }

    public function test_a_signed_delivery_receipt_marks_the_text_delivered(): void
    {
        $publicId = $this->useRealSignatures();
        $recipientId = $this->sentText('SM123');

        $this->deliveryStatus($publicId, 'SM123', 'delivered')->assertOk();

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame('SENT', $recipient->status);
        $this->assertNotNull($recipient->delivered_at);
    }

    public static function receiptsAfterTheCampaignFinished(): array
    {
        return [
            'delivered' => ['delivered', ['sent' => 1, 'delivered' => 1, 'failed' => 0]],
            'undelivered' => ['undelivered', ['sent' => 0, 'delivered' => 0, 'failed' => 1]],
        ];
    }

    #[DataProvider('receiptsAfterTheCampaignFinished')]
    public function test_a_delivery_receipt_arriving_after_the_campaign_finished_shows_in_the_next_report(string $status, array $expected): void
    {
        $publicId = $this->useRealSignatures();
        $campaignId = $this->smsCampaign(['status' => 'SENT', 'sent_at' => now()]);
        $this->insertRecipient($campaignId, $this->irishId, 'ada@example.com', ['phone' => '+353851234567', 'phone_country' => 'IE', 'status' => 'SENT', 'provider_message_id' => 'SM123']);
        $this->crm('GET', "campaigns/$campaignId", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.stats.sent', 1)
            ->assertJsonPath('data.stats.delivered', 0);

        $this->deliveryStatus($publicId, 'SM123', $status)->assertOk();

        $stats = $this->crm('GET', "campaigns/$campaignId", [], $this->ownerHeaders())->assertOk()->json('data.stats');
        $this->assertEquals($expected, array_intersect_key($stats, $expected));
    }

    #[DataProvider('failedStatuses')]
    public function test_a_text_the_carrier_could_not_deliver_is_marked_failed(string $status): void
    {
        $publicId = $this->useRealSignatures();
        $recipientId = $this->sentText('SM123');

        $this->deliveryStatus($publicId, 'SM123', $status)->assertOk();

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame(['FAILED', 'UNDELIVERED'], [$recipient->status, $recipient->failure_reason]);
        $this->assertNull($recipient->delivered_at);
    }

    public static function failedStatuses(): array
    {
        return [
            'failed' => ['failed'],
            'undelivered' => ['Undelivered'],
        ];
    }

    #[DataProvider('statusesInTransit')]
    public function test_a_receipt_for_a_text_still_in_transit_changes_nothing(string $status): void
    {
        $publicId = $this->useRealSignatures();
        $recipientId = $this->sentText('SM123');

        $this->deliveryStatus($publicId, 'SM123', $status)->assertOk();

        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertSame('SENT', $recipient->status);
        $this->assertNull($recipient->delivered_at);
    }

    public static function statusesInTransit(): array
    {
        return [
            'queued' => ['queued'],
            'sent' => ['sent'],
        ];
    }

    public function test_a_delivery_receipt_cannot_touch_another_organizers_text(): void
    {
        $publicId = $this->useRealSignatures();
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $otherCampaignId = $this->insertCampaign(['channel' => 'SMS', 'subject' => null, 'status' => 'SENDING'], $otherOrganizerId);
        $otherRecipientId = $this->insertRecipient($otherCampaignId, null, 'other@example.com', ['phone' => '+353852222222', 'phone_country' => 'IE', 'status' => 'SENT', 'provider_message_id' => 'SM999'], $otherOrganizerId);

        $this->deliveryStatus($publicId, 'SM999', 'failed')->assertOk();
        $this->deliveryStatus($publicId, 'SM999', 'delivered')->assertOk();

        $recipient = DB::table('crm_campaign_recipients')->where('id', $otherRecipientId)->first();
        $this->assertSame('SENT', $recipient->status);
        $this->assertNull($recipient->delivered_at);
    }

    public function test_a_delivery_receipt_with_a_wrong_signature_is_refused(): void
    {
        $publicId = $this->useRealSignatures();
        $recipientId = $this->sentText('SM123');

        $this->post($this->webhookUrl($publicId, 'status'), ['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'], ['X-Twilio-Signature' => 'wrong'])->assertUnauthorized();

        $this->assertNull(DB::table('crm_campaign_recipients')->where('id', $recipientId)->value('delivered_at'));
    }

    public function test_the_opt_out_link_unsubscribes_the_number_from_texts_only(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        DB::table('orders')->where('email', 'ada@example.com')->update(['opted_into_marketing_at' => now()->subDays(5)]);
        $this->projectOrders(...DB::table('orders')->where('email', 'ada@example.com')->pluck('id')->all());
        $this->launch($this->smsCampaign());
        $token = app(CampaignUnsubscribeTokenService::class)->shortTokenFor((int) DB::table('crm_campaign_recipients')->value('id'));

        $status = app(CampaignUnsubscribeService::class)->unsubscribe($token);

        $this->assertTrue($status->is_unsubscribed);
        $this->assertSame('SMS', $status->channel);
        $this->assertFalse($status->can_resubscribe);
        $contact = $this->contact('ada@example.com');
        $this->assertSame('UNSUBSCRIBED', $contact->sms_marketing_status);
        $this->assertSame('SUBSCRIBED', $contact->email_marketing_status);
    }

    public function test_texts_cannot_be_sent_without_the_feature_or_a_connection(): void
    {
        $campaignId = $this->smsCampaign();
        app(SmsConnectionService::class)->disconnect($this->organizerId);

        $this->assertNotSendable($campaignId, 'Connect Twilio');

        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, false);
        $this->assertNotSendable($campaignId, 'aren\'t available');
    }

    private function previewFor(int $campaignId, string $body, bool $canViewFinancials): SmsPreviewDTO
    {
        return app(PreviewSmsCampaignDraftHandler::class)->handle(new PreviewSmsCampaignDraftDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            body: $body,
            event_id: null,
            early_access_product_ids: [],
            campaign_id: $campaignId,
            can_view_financials: $canViewFinancials,
            viewer: new CampaignRecipientDTO(first_name: 'Grace', last_name: 'Hopper'),
        ));
    }

    private function restoreDeletedContact(string $email): CrmContactDomainObject
    {
        return app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: $email,
            restore_deleted: true,
        ));
    }

    private function sentTextToken(): string
    {
        return app(CampaignUnsubscribeTokenService::class)->shortTokenFor($this->sentText('SM'.uniqid()));
    }

    private function runDispatcher(int $campaignId): void
    {
        (new UniqueLock(app(CacheRepository::class)))->release(new DispatchCampaignJob($campaignId));
        app(CampaignDispatchService::class)->dispatch($campaignId);
    }

    private function textSubscriber(string $email, string $phone, string $country): int
    {
        $this->projectOrders($this->insertOrder(['email' => $email, 'phone' => $phone, 'phone_country' => $country, 'sms_opted_in_at' => now()->subDays(5)]));

        return $this->contactId($email);
    }

    private function smsCampaign(array $attributes = []): int
    {
        return $this->insertCampaign(['channel' => 'SMS', 'subject' => null, 'body' => 'Tickets are on sale', ...$attributes]);
    }

    private function launch(int $campaignId): void
    {
        app(CampaignLaunchService::class)->launch(app(CrmCampaignRepositoryInterface::class)->findById($campaignId));
    }

    private function assertNotSendable(int $campaignId, string $reason): void
    {
        try {
            $this->launch($campaignId);
            $this->fail('The campaign should not have been sendable.');
        } catch (CampaignNotSendableException $exception) {
            $this->assertStringContainsString($reason, $exception->getMessage());
        }
    }

    private function webhookUrl(string $publicId, string $kind): string
    {
        return "/public/webhooks/sms/twilio/{$publicId}/{$kind}";
    }

    private function sign(string $publicId, string $kind, array $params, string $authToken = self::AUTH_TOKEN): string
    {
        $service = app(SmsConnectionService::class);
        $url = $kind === 'inbound' ? $service->inboundUrl($publicId) : $service->statusUrl($publicId);
        ksort($params);

        return base64_encode(hash_hmac('sha1', $url.implode('', array_map(static fn ($key, $value) => $key.$value, array_keys($params), $params)), $authToken, true));
    }

    private function useRealSignatures(): string
    {
        $this->app->instance(SmsProviderInterface::class, app(TwilioSmsProvider::class));

        return app(SmsConnectionService::class)->findForOrganizer($this->organizerId)->getPublicId();
    }

    private function reply(string $publicId, string $from, string $body, array $extra = []): TestResponse
    {
        $reply = ['From' => $from, 'Body' => $body, ...$extra];

        return $this->post($this->webhookUrl($publicId, 'inbound'), $reply, ['X-Twilio-Signature' => $this->sign($publicId, 'inbound', $reply)]);
    }

    private function deliveryStatus(string $publicId, string $messageId, string $status): TestResponse
    {
        $receipt = ['MessageSid' => $messageId, 'MessageStatus' => $status];

        return $this->post($this->webhookUrl($publicId, 'status'), $receipt, ['X-Twilio-Signature' => $this->sign($publicId, 'status', $receipt)]);
    }

    private function sentText(string $providerMessageId): int
    {
        return $this->insertRecipient($this->smsCampaign(['status' => 'SENDING']), $this->irishId, 'ada@example.com', ['phone' => '+353851234567', 'phone_country' => 'IE', 'status' => 'SENT', 'provider_message_id' => $providerMessageId]);
    }

    private function fakeProvider(): object
    {
        return new class implements SmsProviderInterface
        {
            public array $sent = [];

            public ?SmsProviderException $failWith = null;

            public function listSenders(SmsCredentialsDTO $credentials): array
            {
                return [
                    new SmsSenderDTO(sid: 'MG00000000000000000000000000000001', name: 'Box office'),
                    new SmsSenderDTO(sid: 'MG00000000000000000000000000000002', name: 'Other box office'),
                ];
            }

            public function findSender(SmsCredentialsDTO $credentials): ?SmsSenderDTO
            {
                return collect($this->listSenders($credentials))->first(static fn (SmsSenderDTO $sender) => $sender->sid === $credentials->messaging_service_sid);
            }

            public function configureInbound(SmsCredentialsDTO $credentials, string $inboundUrl): void {}

            public function send(SmsCredentialsDTO $credentials, string $to, string $body, ?string $statusCallbackUrl): string
            {
                if ($this->failWith !== null) {
                    throw $this->failWith;
                }

                $this->sent[] = ['to' => $to, 'body' => $body, 'status_callback' => $statusCallbackUrl];

                return 'SM'.count($this->sent);
            }

            public function isValidSignature(string $authToken, string $url, array $params, string $signature): bool
            {
                return false;
            }
        };
    }
}
