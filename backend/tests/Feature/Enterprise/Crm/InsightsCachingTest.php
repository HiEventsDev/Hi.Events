<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Closure;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\MergeContactsHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SetPrimaryContactEmailHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\SplitContactEmailHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\GetEventAudienceHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\GetLeadTimeHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\ConfirmSubscriptionHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignFeedbackService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeTokenService;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentCountService;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SubscriptionTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InsightsCachingTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    private string $versionBeforeWrite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
    }

    public function test_a_member_without_financial_access_never_gets_revenue_concentration_cached_for_the_owner(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);

        $this->crm('GET', 'insights/overview', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.revenue_concentration.0.currency', 'USD');

        $this->crm('GET', 'insights/overview', [], $this->memberHeaders($member))
            ->assertOk()
            ->assertJsonPath('data.revenue_concentration', null)
            ->assertJsonPath('data.buyers_count', 1);
    }

    public function test_the_overview_and_retention_include_a_new_order_as_soon_as_it_is_projected(): void
    {
        $this->crm('GET', 'insights/overview', [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.buyers_count', 1);
        $before = $this->retentionSize();

        $this->projectOrders($this->insertOrder(['email' => 'newcomer@example.com', 'created_at' => now(), 'updated_at' => now()]));

        $this->crm('GET', 'insights/overview', [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.buyers_count', 2);
        $this->assertSame($before + 1, $this->retentionSize());
    }

    public function test_the_overview_counts_the_live_events_that_ask_buyers_to_opt_in(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $this->insertEventSettings($this->eventId, true);

        $notAsking = $this->createEvent($this->accountId, $this->organizerId, 'No opt-in');
        DB::table('events')->where('id', $notAsking)->update(['status' => 'LIVE']);
        $this->insertOccurrence($notAsking, now()->addDays(5));
        $this->insertEventSettings($notAsking, false);

        $ended = $this->createEvent($this->accountId, $this->organizerId, 'Ended');
        DB::table('events')->where('id', $ended)->update(['status' => 'LIVE']);
        $this->insertOccurrence($ended, now()->subDays(5));
        $this->insertEventSettings($ended, false);

        $draft = $this->createEvent($this->accountId, $this->organizerId, 'Draft');
        $this->insertOccurrence($draft, now()->addDays(5));
        $this->insertEventSettings($draft, false);

        $elsewhere = $this->createEvent($this->accountId, $this->otherOrganizerId, 'Elsewhere');
        DB::table('events')->where('id', $elsewhere)->update(['status' => 'LIVE']);
        $this->insertOccurrence($elsewhere, now()->addDays(5));
        $this->insertEventSettings($elsewhere, false);

        $this->crm('GET', 'insights/overview', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.live_events_count', 2)
            ->assertJsonPath('data.live_events_collecting_opt_in', 1)
            ->assertJsonPath('data.live_events_not_collecting_opt_in', [['id' => $notAsking, 'title' => 'No opt-in']]);

        DB::table('event_settings')->where('event_id', $notAsking)->update(['show_marketing_opt_in' => true]);

        $this->crm('GET', 'insights/overview', [], $this->ownerHeaders())
            ->assertJsonPath('data.live_events_collecting_opt_in', 2)
            ->assertJsonPath('data.live_events_not_collecting_opt_in', []);
    }

    public function test_the_overview_names_at_most_five_of_the_live_events_not_asking_buyers_to_opt_in(): void
    {
        $notAsking = array_map(function (int $index): int {
            $eventId = $this->createEvent($this->accountId, $this->organizerId, "Show $index");
            DB::table('events')->where('id', $eventId)->update(['status' => 'LIVE']);
            $this->insertOccurrence($eventId, now()->addDays(5));
            $this->insertEventSettings($eventId, false);

            return $eventId;
        }, range(1, 6));

        $overview = $this->crm('GET', 'insights/overview', [], $this->ownerHeaders())->assertOk()->json('data');

        $this->assertSame(6, $overview['live_events_count'] - $overview['live_events_collecting_opt_in']);
        $this->assertSame(array_slice(array_reverse($notAsking), 0, 5), array_column($overview['live_events_not_collecting_opt_in'], 'id'));
    }

    public function test_the_event_audience_and_lead_time_are_cached_until_a_projected_order_changes_them(): void
    {
        $this->assertSame(1, app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId)->contacts_count);
        $this->assertSame(1, app(GetLeadTimeHandler::class)->handle($this->organizerId)->tickets_count);

        DB::table('crm_contact_activities')->where('organizer_id', $this->organizerId)->delete();

        $this->assertSame(1, app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId)->contacts_count);
        $this->assertSame(1, app(GetLeadTimeHandler::class)->handle($this->organizerId)->tickets_count);

        $newcomer = $this->insertOrder(['email' => 'newcomer@example.com']);
        $this->insertAttendee($newcomer);
        $this->projectOrders($this->orderId, $newcomer);

        $this->assertSame(2, app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId)->contacts_count);
        $this->assertSame(2, app(GetLeadTimeHandler::class)->handle($this->organizerId)->tickets_count);
    }

    public function test_re_projecting_an_order_that_did_not_change_keeps_the_cache(): void
    {
        $waitlistEntryId = $this->insertWaitlistEntry(['email' => 'wanda@example.com']);
        $this->projectWaitlistEntries($waitlistEntryId);
        $version = $this->cacheVersion();

        $this->projectOrders($this->orderId);
        $this->projectWaitlistEntries($waitlistEntryId);

        $this->assertSame($version, $this->cacheVersion());
    }

    public function test_re_projecting_an_order_that_changed_refreshes_the_cache(): void
    {
        $version = $this->cacheVersion();

        DB::table('orders')->where('id', $this->orderId)->update(['status' => 'CANCELLED', 'updated_at' => now()]);
        $this->projectOrders($this->orderId);

        $this->assertNotSame($version, $this->cacheVersion());
    }

    public function test_re_projecting_an_order_that_repairs_a_contact_refreshes_the_cache(): void
    {
        DB::table('crm_contacts')->where('id', $this->contactId)->update(['orders_count' => 99]);
        $version = $this->cacheVersion();

        $this->projectOrders($this->orderId);

        $this->assertNotSame($version, $this->cacheVersion());
        $this->assertSame(1, (int) DB::table('crm_contacts')->where('id', $this->contactId)->value('orders_count'));
    }

    public function test_segment_counts_are_cached_until_the_organizers_audience_changes(): void
    {
        $definition = ['match' => 'all', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]];
        $counts = app(SegmentCountService::class);

        $this->assertSame(1, $counts->count($this->organizerId, $definition)->contacts_count);

        $this->insertContact('grace@example.com', ['orders_count' => 1]);
        $this->assertSame(1, $counts->count($this->organizerId, $definition)->contacts_count);

        $this->projectOrders($this->insertOrder(['email' => 'lin@example.com']));
        $this->assertSame(3, $counts->count($this->organizerId, $definition)->contacts_count);

        $this->insertContact('kai@example.com', ['orders_count' => 1]);
        app(CrmCacheVersion::class)->bump($this->organizerId);
        $this->assertSame(4, $counts->count($this->organizerId, $definition)->contacts_count);
    }

    public static function audienceWrites(): array
    {
        $selection = static fn (self $test) => ['mode' => 'ids', 'contact_ids' => [$test->contactId], 'confirmation_count' => 1];
        $csvUpload = static fn (string $csv) => UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        return [
            'merging two contacts' => [static fn (self $test) => app(MergeContactsHandler::class)->handle($test->organizerId, $test->contactId, $test->secondContactId)],
            'splitting an address off' => [static fn (self $test) => app(SplitContactEmailHandler::class)->handle($test->organizerId, $test->contactId, $test->aliasEmailId)],
            'changing the primary address' => [static fn (self $test) => app(SetPrimaryContactEmailHandler::class)->handle($test->organizerId, $test->contactId, $test->aliasEmailId)],
            'renaming a contact' => [static fn (self $test) => $test->crm('PUT', "contacts/{$test->contactId}", ['first_name' => 'Augusta', 'last_name' => 'King'], $test->ownerHeaders())->assertOk()],
            'deleting a contact' => [static fn (self $test) => $test->crm('DELETE', "contacts/{$test->contactId}", [], $test->ownerHeaders())->assertNoContent()],
            'renaming a tag' => [static fn (self $test) => $test->crm('PUT', "tags/{$test->tagId}", ['name' => 'Very Important'], $test->ownerHeaders())->assertOk()],
            'deleting a tag' => [static fn (self $test) => $test->crm('DELETE', "tags/{$test->tagId}", [], $test->ownerHeaders())->assertNoContent()],
            'tagging in bulk' => [static fn (self $test) => $test->crm('POST', 'contacts/bulk/tags', [...$selection($test), 'action' => 'ADD_TAGS', 'tag_ids' => [$test->tagId]], $test->ownerHeaders())->assertCreated()],
            'undoing a bulk tag' => [static function (self $test) use ($selection): void {
                $operationId = $test->crm('POST', 'contacts/bulk/tags', [...$selection($test), 'action' => 'ADD_TAGS', 'tag_ids' => [$test->tagId]], $test->ownerHeaders())->json('data.id');
                $test->versionBeforeWrite = $test->cacheVersion();
                $test->crm('POST', "bulk-operations/$operationId/undo", [], $test->ownerHeaders())->assertOk();
            }],
            'unsubscribing in bulk' => [static fn (self $test) => $test->crm('POST', 'contacts/bulk/unsubscribe', $selection($test), $test->ownerHeaders())->assertCreated()],
            'deleting in bulk' => [static fn (self $test) => $test->crm('POST', 'contacts/bulk/delete', $selection($test), $test->ownerHeaders())->assertCreated()],
            'importing contacts' => [static fn (self $test) => $test->crm('POST', 'imports', ['file' => $csvUpload("Email\nimported@example.com\n")], $test->ownerHeaders())->assertCreated()],
            'importing suppressions' => [static fn (self $test) => $test->crm('POST', 'imports', ['file' => $csvUpload("Email\nada@example.com\n"), 'kind' => 'SUPPRESSIONS'], $test->ownerHeaders())->assertCreated()],
            'reactivating a bounce' => [static function (self $test): void {
                $test->insertConsent($test->contactId, 'ada@example.com', 'BOUNCED', now()->subDays(40));
                $emailId = DB::table('crm_contact_emails')->where('contact_id', $test->contactId)->where('email', 'ada@example.com')->value('id');
                $test->crm('POST', "contacts/{$test->contactId}/emails/$emailId/clear-bounce", [], $test->ownerHeaders())->assertOk();
            }],
            'confirming a signup' => [static function (self $test): void {
                $token = app(SubscriptionTokenService::class)->generate();
                DB::table('crm_pending_subscriptions')->insert([
                    'organizer_id' => $test->organizerId,
                    'form_id' => $test->signupFormId,
                    'email' => 'signup@example.com',
                    'token_hash' => $token->hash,
                    'status' => 'PENDING',
                    'expires_at' => now()->addDays(7),
                    'created_at' => now(),
                ]);
                app(ConfirmSubscriptionHandler::class)->handle($token->token);
            }],
            'unsubscribing by link' => [static function (self $test): void {
                $recipientId = $test->insertRecipient($test->campaignId, $test->contactId, 'ada@example.com', ['status' => 'SENT']);
                app(CampaignUnsubscribeService::class)->unsubscribe(app(CampaignUnsubscribeTokenService::class)->tokenFor($recipientId));
            }],
            'a recorded bounce' => [static function (self $test): void {
                $test->insertRecipient($test->campaignId, $test->contactId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'm-ada']);
                $test->insertDelivery('m-ada', ['bounced_at' => now(), 'bounce_type' => 'PERMANENT']);
                app(CampaignFeedbackService::class)->recordBouncesAndComplaints();
            }],
            'opting out of texts' => [static fn (self $test) => app(ConsentRecorder::class)->recordSmsConsent($test->organizerId, $test->contactId, '+353851234567', CrmConsentAction::OPT_OUT, CrmConsentSource::SMS_REPLY)],
        ];
    }

    #[DataProvider('audienceWrites')]
    public function test_every_write_that_changes_the_audience_refreshes_cached_counts(Closure $write): void
    {
        $this->versionBeforeWrite = $this->cacheVersion();

        $write($this);

        $this->assertNotSame($this->versionBeforeWrite, $this->cacheVersion());
    }

    private function cacheVersion(): string
    {
        return app(CrmCacheVersion::class)->current($this->organizerId);
    }

    private function retentionSize(): int
    {
        return (int) collect($this->crm('GET', 'insights/retention', [], $this->ownerHeaders())->assertOk()->json('data.cohorts'))->sum('size');
    }

    private function insertEventSettings(int $eventId, bool $showMarketingOptIn): void
    {
        DB::table('event_settings')->insert([
            'event_id' => $eventId,
            'show_marketing_opt_in' => $showMarketingOptIn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
