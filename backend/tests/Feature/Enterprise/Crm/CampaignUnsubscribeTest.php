<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignUnsubscribeTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    private int $sentCampaignId;

    private int $recipientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
        $this->sentCampaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour()]);
        $this->recipientId = $this->insertRecipient($this->sentCampaignId, $this->contactId, 'ada@example.com', ['status' => 'SENT']);
    }

    public function test_looking_at_the_unsubscribe_page_changes_nothing_and_masks_the_email(): void
    {
        $before = $this->crmState();

        $this->unsubscribe('GET', $this->token())
            ->assertOk()
            ->assertExactJson(['data' => ['organizer_name' => 'Test Organizer', 'email' => 'a•••••@example.com', 'is_unsubscribed' => false, 'can_resubscribe' => false, 'channel' => 'EMAIL', 'phone' => null]]);

        $this->assertSame($before, $this->crmState());
        $this->assertSame('SUBSCRIBED', $this->marketingStatus());
    }

    public function test_posting_the_link_unsubscribes_without_signing_in_and_is_idempotent(): void
    {
        $response = $this->unsubscribe('POST', $this->token())->assertOk();
        $this->unsubscribe('POST', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $response->assertExactJson(['data' => ['organizer_name' => 'Test Organizer', 'email' => 'a•••••@example.com', 'is_unsubscribed' => true, 'can_resubscribe' => true, 'channel' => 'EMAIL', 'phone' => null]]);
        $this->assertStringNotContainsString('ada@example.com', (string) $response->getContent());
        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
        $event = DB::table('crm_consent_events')->where('contact_id', $this->contactId)->where('action', 'OPT_OUT')->sole();
        $this->assertSame(['UNSUBSCRIBE_LINK', $this->recipientId, null, 'ada@example.com'], [$event->source_type, (int) $event->source_id, $event->user_id, $event->email]);
        $this->unsubscribe('GET', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', true);
    }

    public function test_the_one_click_header_url_points_at_the_public_unsubscribe_route(): void
    {
        config(['app.url' => 'https://tickets.example.com', 'app.api_url' => 'https://tickets.example.com', 'app.frontend_url' => 'https://tickets.example.com']);
        $publicApi = 'https://tickets.example.com/api';

        $url = app(CampaignUnsubscribeTokenService::class)->oneClickUrl($this->recipientId);

        $this->assertStringStartsWith($publicApi.'/public/crm/unsubscribe/', $url);
        $this->post(substr($url, strlen($publicApi)), ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
    }

    public static function badTokens(): array
    {
        return [
            'tampered signature' => ['tampered'],
            'signature of another recipient' => ['swapped'],
            'unknown recipient with a valid signature' => ['unknown'],
            'no signature' => ['unsigned'],
            'garbage' => ['garbage'],
        ];
    }

    #[DataProvider('badTokens')]
    public function test_a_tampered_or_unknown_token_is_not_found_and_changes_nothing(string $case): void
    {
        $tokens = app(CampaignUnsubscribeTokenService::class);
        $otherRecipientId = $this->insertRecipient($this->sentCampaignId, $this->secondContactId, 'second@example.com', ['status' => 'SENT']);
        $token = match ($case) {
            'tampered' => substr($this->token(), 0, -1).(str_ends_with($this->token(), 'a') ? 'b' : 'a'),
            'swapped' => $this->recipientId.'.'.explode('.', $tokens->tokenFor($otherRecipientId))[1],
            'unknown' => $tokens->tokenFor(999999999),
            'unsigned' => (string) $this->recipientId,
            'garbage' => 'not-a-token',
        };
        $before = $this->crmState();

        $this->unsubscribe('GET', $token)->assertNotFound();
        $this->unsubscribe('POST', $token)->assertNotFound();

        $this->assertSame($before, $this->crmState());
    }

    public function test_an_opt_out_wins_over_the_earlier_opt_in_and_later_campaigns_skip_the_contact(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        config(['app.saas_mode_enabled' => false]);
        $this->verifyAccount(attributes: ['account_messaging_tier_id' => $this->messagingTierId('Trusted')]);
        $this->insertSubscribedContact('grace@example.com');

        $this->unsubscribe('POST', $this->token())->assertOk();
        $this->projectOrders($this->orderId);

        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
        $nextId = $this->insertCampaign();
        $this->crm('POST', "campaigns/$nextId/send", [], $this->ownerHeaders())->assertOk();
        $this->assertSame(['grace@example.com'], DB::table('crm_campaign_recipients')->where('campaign_id', $nextId)->pluck('email')->all());
    }

    public function test_using_an_old_link_again_after_opting_back_in_unsubscribes_again(): void
    {
        $this->unsubscribe('POST', $this->token())->assertOk();
        $this->travel(1)->days();
        $laterOrderId = $this->insertOrder(['opted_into_marketing_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->projectOrders($laterOrderId);
        $this->assertSame('SUBSCRIBED', $this->marketingStatus());
        $this->travel(1)->days();

        $this->unsubscribe('POST', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
    }

    public function test_unsubscribing_twice_through_the_same_link_keeps_one_consent_event_and_moves_it_forward(): void
    {
        $this->unsubscribe('POST', $this->token())->assertOk();
        $first = DB::table('crm_consent_events')->where('source_type', 'UNSUBSCRIBE_LINK')->sole();
        $this->travel(2)->days();

        $this->unsubscribe('POST', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $second = DB::table('crm_consent_events')->where('source_type', 'UNSUBSCRIBE_LINK')->sole();
        $this->assertSame([(int) $first->id, 'OPT_OUT', $this->recipientId, $this->contactId], [(int) $second->id, $second->action, (int) $second->source_id, (int) $second->contact_id]);
        $this->assertSame(now()->toDateTimeString(), $second->occurred_at);
        $this->assertTrue($second->occurred_at > $first->occurred_at);
        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
    }

    public function test_a_link_from_another_campaign_records_its_own_opt_out(): void
    {
        $otherCampaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subMinutes(30)]);
        $otherRecipientId = $this->insertRecipient($otherCampaignId, $this->contactId, 'ada@example.com', ['status' => 'SENT']);

        $this->unsubscribe('POST', $this->token())->assertOk();
        $this->unsubscribe('POST', app(CampaignUnsubscribeTokenService::class)->tokenFor($otherRecipientId))->assertOk();

        $this->assertEqualsCanonicalizing(
            [$this->recipientId, $otherRecipientId],
            DB::table('crm_consent_events')->where('source_type', 'UNSUBSCRIBE_LINK')->pluck('source_id')->map(static fn ($id) => (int) $id)->all(),
        );
    }

    public function test_a_delivered_campaign_cannot_be_deleted_so_its_unsubscribe_link_keeps_working(): void
    {
        $this->crm('DELETE', "campaigns/{$this->sentCampaignId}", [], $this->ownerHeaders())->assertStatus(409);

        $this->assertNotNull($this->campaign($this->sentCampaignId));
        $this->unsubscribe('POST', $this->token())->assertOk();

        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
    }

    public function test_the_link_of_a_deleted_contact_offers_to_unsubscribe_without_showing_an_address_and_looking_changes_nothing(): void
    {
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();
        $before = $this->crmState();

        $this->unsubscribe('GET', $this->token())
            ->assertOk()
            ->assertExactJson(['data' => ['organizer_name' => 'Test Organizer', 'email' => null, 'is_unsubscribed' => false, 'can_resubscribe' => false, 'channel' => 'EMAIL', 'phone' => null]]);

        $this->assertSame($before, $this->crmState());
    }

    public function test_confirming_the_link_of_a_deleted_contact_unsubscribes_them_for_good_and_recreates_nothing(): void
    {
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();

        $this->unsubscribe('POST', $this->token())
            ->assertOk()
            ->assertExactJson(['data' => ['organizer_name' => 'Test Organizer', 'email' => null, 'is_unsubscribed' => true, 'can_resubscribe' => false, 'channel' => 'EMAIL', 'phone' => null]]);
        $this->unsubscribe('GET', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', true)->assertJsonPath('data.can_resubscribe', false);

        $this->assertNull($this->contact('ada@example.com'));
        $this->assertSame(0, DB::table('crm_consent_events')->where('email', 'ada@example.com')->count());
    }

    public static function suppressionsHeldWhenDeleted(): array
    {
        return [
            'unsubscribed' => ['OPT_OUT'],
            'complained' => ['COMPLAINED'],
        ];
    }

    #[DataProvider('suppressionsHeldWhenDeleted')]
    public function test_the_link_of_a_contact_deleted_while_suppressed_says_they_are_unsubscribed(string $action): void
    {
        $this->insertConsent($this->contactId, 'ada@example.com', $action, now()->subMinute());
        $this->refreshContact();
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();

        $this->unsubscribe('GET', $this->token())
            ->assertOk()
            ->assertJsonPath('data.is_unsubscribed', true)
            ->assertJsonPath('data.can_resubscribe', false)
            ->assertJsonPath('data.email', null);
    }

    public function test_the_link_of_a_contact_deleted_while_bounced_still_offers_to_unsubscribe_and_the_opt_out_outlasts_the_bounce(): void
    {
        $this->insertConsent($this->contactId, 'ada@example.com', 'BOUNCED', now()->subMinute());
        $this->refreshContact();
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();

        $this->unsubscribe('GET', $this->token())
            ->assertOk()
            ->assertJsonPath('data.is_unsubscribed', false)
            ->assertJsonPath('data.can_resubscribe', false)
            ->assertJsonPath('data.email', null);
        $this->unsubscribe('POST', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', true);
        $this->unsubscribe('GET', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $this->crm('POST', 'contacts', ['email' => 'ada@example.com', 'restore_deleted' => true], $this->ownerHeaders())->assertCreated();
        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_a_deleted_contact_cannot_opt_back_in_from_the_link_and_is_not_recreated(): void
    {
        $this->unsubscribe('POST', $this->token())->assertOk()->assertJsonPath('data.can_resubscribe', true);
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();
        $before = $this->crmState();

        $this->resubscribe($this->token())->assertStatus(409);

        $this->assertSame($before, $this->crmState());
        $this->crm('POST', 'contacts', ['email' => 'ada@example.com', 'restore_deleted' => true], $this->ownerHeaders())->assertCreated();
        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_unsubscribing_from_the_link_after_the_contact_was_deleted_keeps_them_unsubscribed_when_they_are_added_again(): void
    {
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();

        $this->unsubscribe('POST', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $this->crm('POST', 'contacts', ['email' => 'ada@example.com', 'restore_deleted' => true], $this->ownerHeaders())->assertCreated();
        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_an_old_link_still_unsubscribes_a_contact_who_was_deleted_and_added_again(): void
    {
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();
        $this->crm('POST', 'contacts', ['email' => 'ada@example.com', 'restore_deleted' => true], $this->ownerHeaders())->assertCreated();
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);

        $this->unsubscribe('GET', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', false);
        $this->unsubscribe('POST', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', true);

        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_the_link_keeps_working_after_the_organizer_is_deleted_and_names_the_platform_instead(): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update(['deleted_at' => now()]);
        $platformName = (string) config('app.name');
        $this->assertNotSame('Test Organizer', $platformName);

        $this->unsubscribe('GET', $this->token())
            ->assertOk()
            ->assertExactJson(['data' => ['organizer_name' => $platformName, 'email' => 'a•••••@example.com', 'is_unsubscribed' => false, 'can_resubscribe' => false, 'channel' => 'EMAIL', 'phone' => null]]);
        $this->unsubscribe('POST', $this->token())
            ->assertOk()
            ->assertExactJson(['data' => ['organizer_name' => $platformName, 'email' => 'a•••••@example.com', 'is_unsubscribed' => true, 'can_resubscribe' => true, 'channel' => 'EMAIL', 'phone' => null]]);

        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
        $this->unsubscribe('GET', $this->token())->assertOk()->assertJsonPath('data.is_unsubscribed', true);
    }

    public function test_unsubscribing_works_without_a_licence_or_the_feature_flag(): void
    {
        $this->useLicenceKey(null);

        $this->unsubscribe('POST', $this->token())->assertOk();

        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
    }

    public function test_an_old_link_to_an_address_that_was_merged_away_unsubscribes_the_merged_contact(): void
    {
        $this->insertConsent($this->secondContactId, 'second@example.com', 'OPT_IN', now()->subDays(20));
        $secondRecipientId = $this->insertRecipient($this->sentCampaignId, $this->secondContactId, 'second@example.com', ['status' => 'SENT']);
        $this->crm('POST', "contacts/{$this->contactId}/merge", ['source_contact_id' => $this->secondContactId], $this->ownerHeaders())->assertOk();
        $this->assertSame('SUBSCRIBED', $this->marketingStatus());

        $this->unsubscribe('POST', app(CampaignUnsubscribeTokenService::class)->tokenFor($secondRecipientId))
            ->assertOk()
            ->assertJsonPath('data.is_unsubscribed', true);

        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
    }

    public function test_a_complaint_about_a_secondary_address_suppresses_the_contact(): void
    {
        $this->insertConsent($this->contactId, 'alias@example.com', 'COMPLAINED', now()->subDay());

        $this->refreshContact();

        $this->assertSame('COMPLAINED', $this->marketingStatus());
    }

    public function test_making_an_older_opted_in_address_primary_keeps_a_later_opt_out_elsewhere(): void
    {
        $this->insertConsent($this->contactId, 'alias@example.com', 'OPT_IN', now()->subDays(10));
        $this->unsubscribe('POST', $this->token())->assertOk();

        $this->crm('POST', "contacts/{$this->contactId}/emails/{$this->aliasEmailId}/primary", [], $this->ownerHeaders())->assertOk();

        $this->assertSame('alias@example.com', DB::table('crm_contacts')->where('id', $this->contactId)->value('email'));
        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
    }

    public function test_a_bounce_on_an_old_secondary_address_does_not_suppress_the_primary(): void
    {
        $this->insertConsent($this->contactId, 'alias@example.com', 'BOUNCED', now()->subDay());

        $this->refreshContact();

        $this->assertSame('SUBSCRIBED', $this->marketingStatus());
    }

    public function test_an_opt_in_on_a_secondary_address_does_not_subscribe_the_contact(): void
    {
        $this->unsubscribe('POST', $this->token())->assertOk();
        $this->insertConsent($this->contactId, 'alias@example.com', 'OPT_IN', now()->addMinute());

        $this->refreshContact();

        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
    }

    public function test_the_contact_can_opt_back_in_from_the_link_they_unsubscribed_with(): void
    {
        $this->unsubscribe('POST', $this->token())->assertOk();
        $this->travel(1)->minutes();

        $this->resubscribe($this->token())
            ->assertOk()
            ->assertExactJson(['data' => ['organizer_name' => 'Test Organizer', 'email' => 'a•••••@example.com', 'is_unsubscribed' => false, 'can_resubscribe' => false, 'channel' => 'EMAIL', 'phone' => null]]);

        $this->assertSame('SUBSCRIBED', $this->marketingStatus());
        $event = DB::table('crm_consent_events')->where('contact_id', $this->contactId)->where('source_type', 'CONTACT')->sole();
        $this->assertSame(['OPT_IN', $this->recipientId, null, 'ada@example.com'], [$event->action, (int) $event->source_id, $event->user_id, $event->email]);

        $this->travel(1)->minutes();
        $this->unsubscribe('POST', $this->token())->assertOk();
        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
    }

    public static function optOutsALinkCannotUndo(): array
    {
        return [
            'staff opted them out' => ['MANUAL'],
            'a suppression import' => ['IMPORT'],
            'a link from another campaign' => ['UNSUBSCRIBE_LINK'],
        ];
    }

    #[DataProvider('optOutsALinkCannotUndo')]
    public function test_a_link_cannot_undo_an_opt_out_it_did_not_make(string $source): void
    {
        DB::table('crm_consent_events')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $this->contactId,
            'email' => 'ada@example.com',
            'action' => 'OPT_OUT',
            'source_type' => $source,
            'source_id' => $source === 'MANUAL' ? null : 999999,
            'occurred_at' => now(),
        ]);
        $this->refreshContact();

        $this->unsubscribe('GET', $this->token())->assertOk()->assertJsonPath('data.can_resubscribe', false);
        $this->resubscribe($this->token())->assertStatus(409);

        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus());
    }

    public function test_a_contact_who_complained_cannot_opt_back_in(): void
    {
        $this->insertConsent($this->contactId, 'alias@example.com', 'COMPLAINED', now()->subDay());
        $this->refreshContact();
        $before = $this->crmState();

        $this->unsubscribe('GET', $this->token())->assertOk()->assertJsonPath('data.can_resubscribe', false);
        $this->resubscribe($this->token())->assertStatus(409);

        $this->assertSame($before, $this->crmState());
        $this->assertSame('COMPLAINED', $this->marketingStatus());
    }

    public function test_a_link_to_an_address_that_is_no_longer_primary_cannot_opt_back_in(): void
    {
        $this->unsubscribe('POST', $this->token())->assertOk();
        $this->crm('POST', "contacts/{$this->contactId}/emails/{$this->aliasEmailId}/primary", [], $this->ownerHeaders())->assertOk();

        $this->unsubscribe('GET', $this->token())->assertOk()->assertJsonPath('data.can_resubscribe', false);
        $this->resubscribe($this->token())->assertStatus(409);

        $this->assertSame(0, DB::table('crm_consent_events')->where('source_type', 'CONTACT')->count());
    }

    public function test_a_bad_token_cannot_opt_anyone_in(): void
    {
        $this->resubscribe('not-a-token')->assertNotFound();
        $this->resubscribe(app(CampaignUnsubscribeTokenService::class)->tokenFor(999999999))->assertNotFound();
    }

    private function refreshContact(): void
    {
        app(CrmProjectionRepositoryInterface::class)->refreshContacts($this->organizerId, [$this->contactId]);
    }

    private function resubscribe(string $token): TestResponse
    {
        return $this->json('POST', '/public/crm/unsubscribe/'.$token.'/resubscribe');
    }

    private function token(): string
    {
        return app(CampaignUnsubscribeTokenService::class)->tokenFor($this->recipientId);
    }

    private function unsubscribe(string $method, string $token): TestResponse
    {
        return $this->json($method, '/public/crm/unsubscribe/'.$token);
    }

    private function marketingStatus(): string
    {
        return DB::table('crm_contacts')->where('id', $this->contactId)->value('email_marketing_status');
    }
}
