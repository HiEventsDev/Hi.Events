<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\CrmSegmentPreset;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\Crm\Jobs\SeedContactSyncJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\UpsertSegmentHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag\UpsertTagHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use HiEvents\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrmHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    private ?User $restrictedMember = null;

    private ?array $otherAccount = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
    }

    public function test_the_access_matrix_lists_every_registered_crm_route(): void
    {
        $listed = array_keys($this->crmRoutes());
        sort($listed);

        $this->assertSame($this->registeredCrmRoutes(), $listed);
    }

    public function test_every_route_requires_a_signed_in_user(): void
    {
        $before = $this->crmState();

        foreach ($this->crmRoutes() as $name => [$method, $path, $body]) {
            $this->assertSame(401, $this->crm($method, $path, $body, [])->getStatusCode(), $name);
        }

        $this->assertSame($before, $this->crmState());
    }

    public function test_a_member_granted_the_organizer_can_read_everything(): void
    {
        $member = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerId]);

        foreach ($this->crmRoutes() as $name => [$method, $path, $body]) {
            if ($method === 'GET') {
                $this->assertSame(200, $this->crm($method, $path, $body, $this->memberHeaders($member))->getStatusCode(), $name);
            }
        }
    }

    public static function outsiders(): array
    {
        return [
            'member whose only grant is an event of the organizer' => ['event_grant'],
            'member granted a different organizer' => ['other_organizer_grant'],
            'owner of another account' => ['other_account'],
        ];
    }

    #[DataProvider('outsiders')]
    public function test_every_route_refuses_someone_without_access_to_the_organizer(string $outsider): void
    {
        Bus::fake();
        $headers = match ($outsider) {
            'event_grant' => fn () => $this->memberHeaders($this->addRestrictedAccountUserOnce(eventIds: [$this->eventId])),
            'other_organizer_grant' => fn () => $this->memberHeaders($this->addRestrictedAccountUserOnce(organizerIds: [$this->otherOrganizerId])),
            'other_account' => fn () => $this->otherAccountHeaders(),
        };
        $before = $this->crmState();

        foreach ($this->crmRoutes() as $name => [$method, $path, $body]) {
            $response = $this->crm($method, $path, $body, $headers());

            $this->assertContains($response->getStatusCode(), $outsider === 'other_account' ? [403, 404] : [403], $name.' returned '.$response->getStatusCode());
            $this->assertNotSame('FEATURE_UNAVAILABLE', $response->json('error_code'), $name);
            $this->assertNotSame('READ_ONLY_ROLE', $response->json('error_code'), $name);
        }

        $this->assertSame($before, $this->crmState());
        Bus::assertNothingBatched();
    }

    public function test_a_member_without_edit_permission_reads_but_cannot_write(): void
    {
        Bus::fake();
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $before = $this->crmState();

        foreach ($this->crmRoutes() as $name => [$method, $path, $body]) {
            $response = $this->crm($method, $path, $body, $this->memberHeaders($viewer));

            if ($method === 'GET' || in_array($name, ['POST crm/contacts/export', 'POST crm/suppressions/lookup'], true)) {
                $this->assertSame(200, $response->getStatusCode(), $name);

                continue;
            }

            $this->assertSame(403, $response->getStatusCode(), $name);
            $this->assertSame('READ_ONLY_ROLE', $response->json('error_code'), $name);
        }

        $this->assertSame($before, $this->crmState());
        Bus::assertNothingBatched();
    }

    public function test_a_contacts_activity_says_whether_a_campaign_reached_them_by_email_or_by_text(): void
    {
        foreach (['Doors text' => 'SMS', 'Spring newsletter' => 'EMAIL'] as $name => $channel) {
            $recipientId = $this->insertRecipient(
                $this->insertCampaign(['name' => $name, 'channel' => $channel, 'status' => 'SENT', 'sent_at' => now()]),
                $this->contactId,
                'ada@example.com',
                ['status' => 'SENT'],
            );
            app(CrmCampaignRepositoryInterface::class)->recordCampaignActivity($recipientId, now());
        }

        $activities = collect($this->crm('GET', "contacts/{$this->contactId}/activities", [], $this->ownerHeaders())->assertOk()->json('data'));

        $this->assertSame(
            ['Doors text' => 'SMS', 'Spring newsletter' => 'EMAIL'],
            $activities->where('type', 'CAMPAIGN_SENT')->pluck('campaign_channel', 'label')->sortKeys()->all(),
        );
        $this->assertNull($activities->firstWhere('type', 'ORDER_COMPLETED')['campaign_channel']);
    }

    public function test_members_who_can_view_financials_see_spend_amounts_and_revenue(): void
    {
        $headers = fn () => $this->ownerHeaders();

        $this->crm('GET', 'contacts?query=ada', [], $headers())
            ->assertOk()
            ->assertJsonPath('data.0.spend', [['currency' => 'USD', 'total_gross' => 120, 'total_refunded' => 20, 'net' => 100]]);
        $this->crm('GET', "contacts/{$this->contactId}", [], $headers())
            ->assertOk()
            ->assertJsonPath('data.spend.0.net', 100);

        $activities = collect($this->crm('GET', "contacts/{$this->contactId}/activities", [], $headers())->assertOk()->json('data'))->keyBy('type');
        $this->assertEquals(120, $activities['ORDER_COMPLETED']['amount']);
        $this->assertSame('USD', $activities['ORDER_COMPLETED']['currency']);
        $this->assertEquals(20, $activities['ORDER_REFUNDED']['amount']);
        $this->assertArrayHasKey('amount', $activities['TICKET_HELD']);

        $this->crm('GET', 'insights/overview', [], $headers())
            ->assertOk()
            ->assertJsonPath('data.revenue_concentration.0.currency', 'USD')
            ->assertJsonPath('data.revenue_concentration.0.total_net', 100);
        $this->crm('GET', 'segments/count?'.http_build_query(['definition' => $this->spendDefinition()]), [], $headers())
            ->assertOk()
            ->assertJsonPath('data.contacts_count', 1);
    }

    public function test_members_without_view_financials_get_no_spend_amounts_or_revenue(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $headers = fn () => $this->memberHeaders($member);

        $listed = $this->crm('GET', 'contacts?query=ada', [], $headers())->assertOk()->json('data.0');
        $this->assertSame('ada@example.com', $listed['email']);
        $this->assertSame(1, $listed['orders_count']);
        $this->assertArrayNotHasKey('spend', $listed);

        $shown = $this->crm('GET', "contacts/{$this->contactId}", [], $headers())->assertOk()->json('data');
        $this->assertArrayNotHasKey('spend', $shown);
        $this->assertArrayHasKey('emails', $shown);

        $activities = $this->crm('GET', "contacts/{$this->contactId}/activities", [], $headers())->assertOk()->json('data');
        $this->assertCount(3, $activities);
        foreach ($activities as $activity) {
            $this->assertArrayNotHasKey('amount', $activity, $activity['type']);
            $this->assertArrayNotHasKey('currency', $activity, $activity['type']);
        }

        $overview = $this->crm('GET', 'insights/overview', [], $headers())->assertOk()->json('data');
        $this->assertArrayHasKey('revenue_concentration', $overview);
        $this->assertNull($overview['revenue_concentration']);

        foreach (['PUT', 'POST'] as $method) {
            $write = $this->crm($method, $method === 'PUT' ? "contacts/{$this->contactId}" : "contacts/{$this->contactId}/unsubscribe", ['first_name' => 'Ada'], $headers())->assertOk()->json('data');
            $this->assertArrayNotHasKey('spend', $write, $method);
        }
    }

    public function test_members_without_view_financials_cannot_filter_or_save_segments_by_spend(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $headers = fn () => $this->memberHeaders($member);
        $spendSegmentId = $this->insertSegment($this->organizerId, 'Big spenders', $this->spendDefinition()['rules']);
        $query = http_build_query(['definition' => $this->spendDefinition()]);

        $this->crm('GET', "contacts?$query", [], $headers())->assertStatus(422)->assertJsonValidationErrors(['definition.rules.0']);
        $this->crm('GET', "segments/count?$query", [], $headers())->assertStatus(422);
        $this->crm('GET', "contacts?segment_id=$spendSegmentId", [], $headers())->assertStatus(422);
        $this->crm('GET', "segments/count?segment_id=$spendSegmentId", [], $headers())->assertStatus(422);
        $this->crm('POST', 'segments', ['name' => 'Spenders', 'definition' => $this->spendDefinition()], $headers())->assertStatus(422);
        $this->crm('PUT', "segments/{$this->segmentId}", ['name' => 'Spenders', 'definition' => $this->spendDefinition()], $headers())->assertStatus(422);

        $this->crm('GET', "segments/count?segment_id={$this->segmentId}", [], $headers())->assertOk()->assertJsonPath('data.contacts_count', 1);
        $this->assertSame(2, DB::table('crm_segments')->where('organizer_id', $this->organizerId)->count());
        $this->assertSame('Buyers', DB::table('crm_segments')->where('id', $this->segmentId)->value('name'));
    }

    public function test_child_ids_of_another_organizer_are_not_found_and_nothing_is_written(): void
    {
        $contact = "contacts/{$this->contactId}";
        $foreignContact = "contacts/{$this->otherContactId}";
        $segmentBody = ['name' => 'Hijacked', 'definition' => ['rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]]];
        $requests = [
            'show contact' => ['GET', $foreignContact, []],
            'update contact' => ['PUT', $foreignContact, ['first_name' => 'Hijacked']],
            'delete contact' => ['DELETE', $foreignContact, []],
            'unsubscribe contact' => ['POST', "$foreignContact/unsubscribe", []],
            'contact activities' => ['GET', "$foreignContact/activities", []],
            'contact notes' => ['GET', "$foreignContact/notes", []],
            'note on contact' => ['POST', "$foreignContact/notes", ['note' => 'Hijacked']],
            'tags on contact' => ['PUT', "$foreignContact/tags", ['tag_ids' => [$this->tagId]]],
            'merge into contact' => ['POST', "$foreignContact/merge", ['source_contact_id' => $this->contactId]],
            'merge from contact' => ['POST', "$contact/merge", ['source_contact_id' => $this->otherContactId]],
            'update tag' => ['PUT', "tags/{$this->otherTagId}", ['name' => 'Hijacked']],
            'delete tag' => ['DELETE', "tags/{$this->otherTagId}", []],
            'delete note through own contact' => ['DELETE', "$contact/notes/{$this->otherNoteId}", []],
            'delete note through its contact' => ['DELETE', "$foreignContact/notes/{$this->otherNoteId}", []],
            'own note through another own contact' => ['DELETE', "contacts/{$this->secondContactId}/notes/{$this->noteId}", []],
            'update segment' => ['PUT', "segments/{$this->otherSegmentId}", $segmentBody],
            'delete segment' => ['DELETE', "segments/{$this->otherSegmentId}", []],
            'filter contacts by segment' => ['GET', "contacts?segment_id={$this->otherSegmentId}", []],
            'count segment' => ['GET', "segments/count?segment_id={$this->otherSegmentId}", []],
            'split email of foreign contact' => ['POST', "$foreignContact/emails/{$this->otherEmailId}/split", []],
            'split foreign email from own contact' => ['POST', "$contact/emails/{$this->otherEmailId}/split", []],
            'foreign email as primary' => ['POST', "$contact/emails/{$this->otherEmailId}/primary", []],
            'event audience' => ['GET', "insights/events/{$this->otherEventId}", []],
            'show campaign' => ['GET', "campaigns/{$this->otherCampaignId}", []],
            'preview campaign' => ['GET', "campaigns/{$this->otherCampaignId}/preview", []],
            'update campaign' => ['PUT', "campaigns/{$this->otherCampaignId}", ['name' => 'Hijacked']],
            'delete campaign' => ['DELETE', "campaigns/{$this->otherCampaignId}", []],
            'send campaign' => ['POST', "campaigns/{$this->otherCampaignId}/send", []],
            'cancel campaign' => ['POST', "campaigns/{$this->otherCampaignId}/cancel", []],
            'test campaign' => ['POST', "campaigns/{$this->otherCampaignId}/test", []],
            'campaign for a foreign segment' => ['POST', 'campaigns', ['name' => 'Hijacked', 'segment_id' => $this->otherSegmentId]],
            'campaign for a foreign event' => ['POST', 'campaigns', ['name' => 'Hijacked', 'event_id' => $this->otherEventId]],
            'own campaign moved to a foreign segment' => ['PUT', "campaigns/{$this->campaignId}", ['name' => 'Hijacked', 'segment_id' => $this->otherSegmentId]],
            'own campaign moved to a foreign event' => ['PUT', "campaigns/{$this->campaignId}", ['name' => 'Hijacked', 'event_id' => $this->otherEventId]],
            'export a foreign segment' => ['GET', "contacts/export?segment_id={$this->otherSegmentId}", []],
            'missing contact' => ['GET', 'contacts/999999999', []],
        ];
        $before = $this->crmState();

        $statuses = [];
        foreach ($requests as $name => [$method, $path, $body]) {
            $statuses[$name] = $this->crm($method, $path, $body, $this->ownerHeaders())->getStatusCode();
        }

        $this->assertSame($before, $this->crmState());
        $this->assertSame(array_fill_keys(array_keys($requests), 404), $statuses);
    }

    public function test_tagging_a_contact_with_another_organizers_tag_is_a_validation_error_and_writes_nothing(): void
    {
        $before = $this->crmState();

        $this->crm('PUT', "contacts/{$this->contactId}/tags", ['tag_ids' => [$this->tagId, $this->otherTagId]], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids');

        $this->assertSame($before, $this->crmState());
    }

    public function test_segment_options_only_list_products_of_the_organizers_events(): void
    {
        [$foreignProductId] = $this->insertTicket($this->otherEventId);

        $options = $this->crm('GET', 'segment-options?'.http_build_query(['event_ids' => [$this->eventId, $this->otherEventId], 'with_products' => 1, 'product_ids' => [$foreignProductId]]), [], $this->ownerHeaders())
            ->assertOk()
            ->json('data');

        $this->assertSame([$this->eventId], array_column($options['events'], 'id'));
        $this->assertSame([$this->productId], array_column($options['products'], 'id'));
        $this->assertNotContains($foreignProductId, array_column($options['products'], 'id'));
        $this->assertSame(['USD'], $options['currencies']);
    }

    public function test_a_contact_can_be_created_edited_tagged_noted_unsubscribed_and_deleted(): void
    {
        $headers = fn () => $this->ownerHeaders();

        $created = $this->crm('POST', 'contacts', ['email' => '  Grace@Example.com ', 'first_name' => 'Grace', 'last_name' => 'Hopper'], $headers())
            ->assertCreated()
            ->assertJsonPath('data.email', 'grace@example.com')
            ->assertJsonPath('data.source', 'MANUAL')
            ->assertJsonPath('data.email_marketing_status', 'NOT_SUBSCRIBED')
            ->assertJsonPath('data.emails.0.is_primary', true)
            ->json('data');
        $contact = "contacts/{$created['id']}";

        $this->crm('PUT', $contact, ['first_name' => 'Rear Admiral', 'last_name' => 'Hopper'], $headers())
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Rear Admiral Hopper')
            ->assertJsonPath('data.email', 'grace@example.com');

        $this->crm('PUT', "$contact/tags", ['tag_ids' => [$this->tagId]], $headers())
            ->assertOk()
            ->assertJsonPath('data.tags', [['id' => $this->tagId, 'name' => 'VIP', 'color' => null]]);
        $this->crm('GET', 'tags', [], $headers())->assertOk()->assertJsonPath('data.0.contacts_count', 1);
        $this->crm('PUT', "$contact/tags", ['tag_ids' => []], $headers())->assertOk()->assertJsonPath('data.tags', []);

        $noteId = $this->crm('POST', "$contact/notes", ['note' => '  Likes the front row  '], $headers())
            ->assertCreated()
            ->assertJsonPath('data.note', 'Likes the front row')
            ->assertJsonPath('data.author_name', trim($this->owner->first_name.' '.$this->owner->last_name))
            ->json('data.id');
        $this->crm('GET', "$contact/notes", [], $headers())->assertOk()->assertJsonPath('data.0.id', $noteId)->assertJsonCount(1, 'data');
        $this->crm('DELETE', "$contact/notes/$noteId", [], $headers())->assertNoContent();
        $this->crm('GET', "$contact/notes", [], $headers())->assertOk()->assertJsonCount(0, 'data');

        $this->crm('POST', "$contact/unsubscribe", [], $headers())
            ->assertOk()
            ->assertJsonPath('data.email_marketing_status', 'UNSUBSCRIBED');
        $consent = DB::table('crm_consent_events')->where('contact_id', $created['id'])->sole();
        $this->assertSame(['OPT_OUT', 'MANUAL', $this->owner->id], [$consent->action, $consent->source_type, (int) $consent->user_id]);

        $this->crm('DELETE', $contact, [], $headers())->assertNoContent();
        $this->crm('GET', $contact, [], $headers())->assertNotFound();
        $this->assertSame(
            app(DeletedContactHasher::class)->hash($this->organizerId, 'grace@example.com'),
            DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->value('email_hash'),
        );
    }

    public function test_each_segment_and_preset_is_counted_on_its_own(): void
    {
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, true);

        $this->crm('GET', "segments/count?segment_id={$this->segmentId}", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.contacts_count', 1)
            ->assertJsonPath('data.mailable_count', 1);
        $this->crm('GET', 'segments/count?preset=subscribers', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.contacts_count', 1)
            ->assertJsonPath('data.mailable_count', 1);
    }

    public function test_the_text_subscribers_ready_made_segment_is_only_listed_with_the_text_message_feature(): void
    {
        $presetKeys = fn (): array => array_column($this->crm('GET', 'segments', [], $this->ownerHeaders())->assertOk()->json('presets'), 'key');

        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, false);
        $this->assertNotContains('sms_subscribers', $presetKeys());
        $this->assertCount(count(CrmSegmentPreset::cases()) - 1, $presetKeys());

        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, true);
        $this->assertContains('sms_subscribers', $presetKeys());
    }

    public function test_segment_counts_reflect_contact_writes_straight_away(): void
    {
        $count = fn (string $query) => $this->crm('GET', "segments/count?$query", [], $this->ownerHeaders())->assertOk()->json('data');

        $this->assertSame(1, $count('preset=subscribers')['mailable_count']);
        $this->crm('POST', "contacts/{$this->contactId}/unsubscribe", [], $this->ownerHeaders())->assertOk();
        $this->assertSame(0, $count('preset=subscribers')['mailable_count']);

        $tagged = http_build_query(['definition' => ['rules' => [['field' => 'tag', 'operator' => 'in', 'value' => [$this->tagId]]]]]);
        $this->assertSame(0, $count($tagged)['contacts_count']);
        $this->crm('PUT', "contacts/{$this->contactId}/tags", ['tag_ids' => [$this->tagId]], $this->ownerHeaders())->assertOk();
        $this->assertSame(1, $count($tagged)['contacts_count']);

        $everyone = $count('')['contacts_count'];
        $this->crm('POST', 'contacts', ['email' => 'fresh@example.com'], $this->ownerHeaders())->assertCreated();
        $this->assertSame($everyone + 1, $count('')['contacts_count']);
    }

    public function test_deleting_a_contact_leaves_its_orders_and_attendees_untouched(): void
    {
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();

        $this->assertSame(0, DB::table('crm_contacts')->where('id', $this->contactId)->count());
        $this->assertSame(0, DB::table('crm_contact_activities')->where('contact_id', $this->contactId)->count());
        $this->assertSame(1, DB::table('orders')->where('id', $this->orderId)->whereNull('deleted_at')->where('email', 'ada@example.com')->count());
        $this->assertSame(1, DB::table('attendees')->where('order_id', $this->orderId)->whereNull('deleted_at')->count());

        $this->projectOrders($this->orderId);
        $this->assertNull($this->contact('ada@example.com'));

        $this->crm('POST', 'contacts', ['email' => 'ada@example.com'], $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['restore_deleted']);
        $this->projectOrders($this->orderId);
        $this->assertNull($this->contact('ada@example.com'));
        $this->assertTrue(DB::table('crm_deleted_contacts')
            ->where('organizer_id', $this->organizerId)
            ->where('email_hash', app(DeletedContactHasher::class)->hash($this->organizerId, 'ada@example.com'))
            ->exists());

        $this->crm('POST', 'contacts', ['email' => 'ada@example.com', 'restore_deleted' => true], $this->ownerHeaders())->assertCreated();
        $this->projectOrders($this->orderId);
        $this->assertSame(1, (int) $this->contact('ada@example.com')->orders_count);
    }

    public function test_merge_split_and_primary_email_work_over_http(): void
    {
        $headers = fn () => $this->ownerHeaders();
        $contact = "contacts/{$this->contactId}";

        $this->crm('POST', "$contact/merge", ['source_contact_id' => $this->contactId], $headers())->assertStatus(409);
        $this->crm('POST', "$contact/merge", ['source_contact_id' => $this->secondContactId], $headers())
            ->assertOk()
            ->assertJsonPath('data.id', $this->contactId)
            ->assertJsonCount(3, 'data.emails');
        $this->crm('GET', "contacts/{$this->secondContactId}", [], $headers())->assertNotFound();

        $primaryEmailId = DB::table('crm_contact_emails')->where('email', 'ada@example.com')->value('id');
        $this->crm('POST', "$contact/emails/$primaryEmailId/split", [], $headers())->assertStatus(409);

        $this->crm('POST', "$contact/emails/{$this->aliasEmailId}/split", [], $headers())
            ->assertOk()
            ->assertJsonPath('data.id', $this->contactId)
            ->assertJsonCount(2, 'data.emails');
        $this->assertNotNull($this->contact('alias@example.com'));

        $secondEmailId = DB::table('crm_contact_emails')->where('email', 'second@example.com')->value('id');
        $this->crm('POST', "$contact/emails/$secondEmailId/primary", [], $headers())
            ->assertOk()
            ->assertJsonPath('data.email', 'second@example.com')
            ->assertJsonPath('data.email_marketing_status', 'NOT_SUBSCRIBED');
    }

    public function test_splitting_the_only_email_with_history_returns_the_new_contact_when_the_original_is_left_empty(): void
    {
        $originalId = $this->insertContact('unused@example.com', ['source' => 'ORDER']);
        $emailId = $this->insertEmail($this->organizerId, $originalId, 'buyer@example.com');
        $orderId = $this->insertOrder(['email' => 'buyer@example.com', 'first_name' => 'Grace', 'last_name' => 'Hopper']);
        $this->insertAttendee($orderId);
        $this->projectOrders($orderId);
        $this->assertSame(1, (int) DB::table('crm_contacts')->where('id', $originalId)->value('orders_count'));

        $response = $this->crm('POST', "contacts/$originalId/emails/$emailId/split", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.email', 'buyer@example.com')
            ->assertJsonPath('data.orders_count', 1)
            ->assertJsonCount(1, 'data.emails');

        $newContactId = $this->contactId('buyer@example.com');
        $this->assertNotSame($originalId, $newContactId);
        $response->assertJsonPath('data.id', $newContactId);
        $this->assertNull($this->contact('unused@example.com'));
        $this->crm('GET', "contacts/$originalId", [], $this->ownerHeaders())->assertNotFound();
    }

    public function test_the_contact_list_searches_sorts_paginates_and_filters_by_segment(): void
    {
        $headers = fn () => $this->ownerHeaders();
        DB::table('crm_contact_tags')->insert(['contact_id' => $this->secondContactId, 'tag_id' => $this->tagId]);

        $this->crm('GET', 'contacts?sort_by=email&sort_direction=asc', [], $headers())
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.email', 'ada@example.com')
            ->assertJsonPath('data.1.email', 'second@example.com')
            ->assertJsonPath('data.1.tags.0.name', 'VIP');
        $this->crm('GET', 'contacts?sort_by=email&sort_direction=asc&per_page=1&page=2', [], $headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'second@example.com');
        $this->crm('GET', 'contacts?query=ALIAS@', [], $headers())->assertOk()->assertJsonPath('data.0.email', 'ada@example.com')->assertJsonCount(1, 'data');
        $this->crm('GET', 'contacts?query=second+contact', [], $headers())->assertOk()->assertJsonPath('data.0.email', 'second@example.com');
        $this->crm('GET', 'contacts?query='.urlencode('%'), [], $headers())->assertOk()->assertJsonCount(0, 'data');

        $tagged = ['match' => 'all', 'rules' => [['field' => 'tag', 'operator' => 'in', 'value' => [$this->tagId]]]];
        $this->crm('GET', 'contacts?'.http_build_query(['definition' => $tagged]), [], $headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'second@example.com');
        $this->crm('GET', 'contacts?'.http_build_query(['definition' => json_encode($tagged)]), [], $headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'second@example.com');
        $this->crm('GET', "contacts?segment_id={$this->segmentId}", [], $headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'ada@example.com');
        $this->crm('GET', 'contacts?preset=subscribers', [], $headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'ada@example.com');
        $this->crm('GET', 'segments/count?preset=subscribers', [], $headers())
            ->assertOk()->assertExactJson(['data' => ['contacts_count' => 1, 'mailable_count' => 1, 'textable_count' => 0, 'textable_in_enabled_countries_count' => 0]]);
        $this->crm('GET', 'contacts?sort_by=email;drop+table+crm_contacts&sort_direction=sideways', [], $headers())
            ->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_segments_can_be_created_listed_updated_and_deleted(): void
    {
        $headers = fn () => $this->ownerHeaders();
        $definition = ['match' => 'any', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => '1']]];

        $segmentId = $this->crm('POST', 'segments', ['name' => '  Buyers again ', 'description' => 'Anyone who bought', 'definition' => $definition], $headers())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Buyers again')
            ->assertJsonPath('data.definition', ['match' => 'any', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]])
            ->json('data.id');
        $this->crm('GET', "segments/count?segment_id=$segmentId", [], $headers())
            ->assertOk()
            ->assertJsonPath('data.contacts_count', 1)
            ->assertJsonPath('data.mailable_count', 1);

        $listed = $this->crm('GET', 'segments', [], $headers())->assertOk()->json();
        $this->assertEqualsCanonicalizing([$this->segmentId, $segmentId], array_column($listed['data'], 'id'));
        $this->assertCount(count(CrmSegmentPreset::cases()) - 1, $listed['presets']);
        $this->assertSame(['key', 'definition'], array_keys($listed['presets'][0]));

        $this->crm('PUT', "segments/$segmentId", ['name' => 'Nobody', 'definition' => ['rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 50]]]], $headers())
            ->assertOk()
            ->assertJsonPath('data.name', 'Nobody');
        $this->crm('GET', "segments/count?segment_id=$segmentId", [], $headers())->assertOk()->assertJsonPath('data.contacts_count', 0);
        $this->crm('DELETE', "segments/$segmentId", [], $headers())->assertNoContent();
        $this->crm('DELETE', "segments/$segmentId", [], $headers())->assertNotFound();
    }

    public static function invalidWrites(): array
    {
        return [
            'contact without an email' => ['POST', 'contacts', ['first_name' => 'Ada'], 'email'],
            'contact with an invalid email' => ['POST', 'contacts', ['email' => 'not-an-email'], 'email'],
            'contact with an existing email' => ['POST', 'contacts', ['email' => 'ADA@example.com'], 'email'],
            'contact with an existing secondary email' => ['POST', 'contacts', ['email' => 'alias@example.com'], 'email'],
            'contact with a link as a name' => ['POST', 'contacts', ['email' => 'x@example.com', 'first_name' => 'https://spam.example'], 'first_name'],
            'contact with a long name' => ['POST', 'contacts', ['email' => 'x@example.com', 'last_name' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'], 'last_name'],
            'changing a contact email' => ['PUT', 'contacts/{contact}', ['email' => 'changed@example.com'], 'email'],
            'merge without a source' => ['POST', 'contacts/{contact}/merge', [], 'source_contact_id'],
            'merge with a non numeric source' => ['POST', 'contacts/{contact}/merge', ['source_contact_id' => 'abc'], 'source_contact_id'],
            'tags missing' => ['PUT', 'contacts/{contact}/tags', [], 'tag_ids'],
            'tags not a list' => ['PUT', 'contacts/{contact}/tags', ['tag_ids' => 'vip'], 'tag_ids'],
            'tag ids not numeric' => ['PUT', 'contacts/{contact}/tags', ['tag_ids' => ['vip']], 'tag_ids.0'],
            'empty note' => ['POST', 'contacts/{contact}/notes', ['note' => ''], 'note'],
            'tag without a name' => ['POST', 'tags', ['color' => '#ff0000'], 'name'],
            'tag with a duplicate name' => ['POST', 'tags', ['name' => 'vip'], 'name'],
            'tag with a bad colour' => ['POST', 'tags', ['name' => 'Press', 'color' => 'red; background:url(x)'], 'color'],
            'renaming a tag to a long name' => ['PUT', 'tags/{tag}', ['name' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'], 'name'],
            'segment without a name' => ['POST', 'segments', ['definition' => ['rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]]], 'name'],
            'segment without a definition' => ['POST', 'segments', ['name' => 'Fans'], 'definition'],
            'segment without rules' => ['POST', 'segments', ['name' => 'Fans', 'definition' => ['match' => 'all', 'rules' => []]], 'definition.rules'],
            'segment with an unknown field' => ['POST', 'segments', ['name' => 'Fans', 'definition' => ['rules' => [['field' => 'password', 'operator' => 'eq', 'value' => 1]]]], 'definition.rules.0'],
            'segment with a bad match' => ['PUT', 'segments/{segment}', ['name' => 'Fans', 'definition' => ['match' => 'most', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]]], 'definition.match'],
        ];
    }

    #[DataProvider('invalidWrites')]
    public function test_invalid_writes_are_rejected_with_a_field_error_and_change_nothing(string $method, string $path, array $body, string $field): void
    {
        $path = strtr($path, ['{contact}' => $this->contactId, '{tag}' => $this->tagId, '{segment}' => $this->segmentId]);
        $before = $this->crmState();

        $this->crm($method, $path, $body, $this->ownerHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);

        $this->assertSame($before, $this->crmState());
    }

    public static function invalidSegmentQueries(): array
    {
        return [
            'unknown preset' => ['preset=everyone', 404],
            'definition that is not json' => ['definition=orders_count', 422],
            'definition with an unknown operator' => [http_build_query(['definition' => ['rules' => [['field' => 'orders_count', 'operator' => 'like', 'value' => 1]]]]), 422],
            'definition with too many rules' => [http_build_query(['definition' => json_encode(['rules' => array_fill(0, 21, ['field' => 'orders_count', 'operator' => 'gte', 'value' => 1])])]), 422],
            'missing segment' => ['segment_id=999999999', 404],
        ];
    }

    #[DataProvider('invalidSegmentQueries')]
    public function test_invalid_segment_references_are_rejected_on_the_list_and_the_count(string $query, int $status): void
    {
        $this->crm('GET', "contacts?$query", [], $this->ownerHeaders())->assertStatus($status);
        $this->crm('GET', "segments/count?$query", [], $this->ownerHeaders())->assertStatus($status);
    }

    public static function malformedSegmentQueries(): array
    {
        return [
            'preset sent as a list' => ['preset[]=subscribers'],
            'match sent as a list' => ['definition[match][]=all'],
            'field sent as a list' => ['definition[rules][0][field][]=orders_count&definition[rules][0][operator]=gte&definition[rules][0][value]=1'],
            'option value holding a list' => ['definition[rules][0][field]=source&definition[rules][0][operator]=in&definition[rules][0][value][0][]=ORDER'],
        ];
    }

    #[DataProvider('malformedSegmentQueries')]
    public function test_malformed_segment_parameters_are_a_client_error_not_a_server_error(string $query): void
    {
        $status = $this->crm('GET', "segments/count?$query", [], $this->ownerHeaders())->getStatusCode();

        $this->assertContains($status, [404, 422], "segments/count?$query returned $status");
    }

    public function test_the_tag_and_segment_limits_are_enforced(): void
    {
        $now = now();
        DB::table('crm_tags')->insert(array_map(fn (int $index) => [
            'account_id' => $this->accountId, 'organizer_id' => $this->organizerId, 'name' => "Tag $index", 'created_at' => $now, 'updated_at' => $now,
        ], range(1, UpsertTagHandler::MAX_TAGS_PER_ORGANIZER - 1)));
        DB::table('crm_segments')->insert(array_map(fn (int $index) => [
            'account_id' => $this->accountId, 'organizer_id' => $this->organizerId, 'name' => "Segment $index", 'definition' => '{"match":"all","rules":[]}', 'created_at' => $now, 'updated_at' => $now,
        ], range(1, UpsertSegmentHandler::MAX_SEGMENTS_PER_ORGANIZER - 1)));

        $this->crm('POST', 'tags', ['name' => 'One too many'], $this->ownerHeaders())->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->crm('POST', 'segments', ['name' => 'One too many', 'definition' => ['rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]]], $this->ownerHeaders())
            ->assertStatus(409);
        $this->crm('PUT', "tags/{$this->tagId}", ['name' => 'Still editable'], $this->ownerHeaders())->assertOk();
    }

    public function test_sync_status_is_not_started_until_the_organizer_starts_a_sync(): void
    {
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->delete();

        $this->crm('GET', 'sync', [], $this->ownerHeaders())
            ->assertOk()
            ->assertExactJson(['data' => [
                'status' => 'NOT_STARTED',
                'synced_at' => null,
                'contacts_count' => 2,
                'mailable_count' => 1,
                'progress_percent' => 0,
            ]]);
        $this->assertSame(0, DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_starting_a_sync_queues_one_seeder_and_reports_syncing_straight_away(): void
    {
        Bus::fake();
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->delete();
        $this->insertOrder(['email' => 'grace@example.com']);

        $this->crm('POST', 'sync', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'SYNCING')
            ->assertJsonPath('data.synced_at', null);

        Bus::assertBatchCount(1);
        Bus::assertBatched(function (PendingBatch $batch) {
            $seeder = $batch->jobs->first();

            return $batch->name === 'crm-contact-sync-'.$this->organizerId
                && $batch->jobs->count() === 1
                && $seeder instanceof SeedContactSyncJob
                && $seeder->organizerId === $this->organizerId
                && $seeder->accountId === $this->accountId;
        });

        $state = DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->sole();
        $this->assertSame($this->accountId, (int) $state->account_id);
        $this->assertNotNull($state->sync_job_uuid);
        $this->assertNotNull($state->reconciled_until);
        $this->assertNull($state->synced_at);

        $this->crm('POST', 'sync', [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'SYNCING');
        $this->crm('GET', 'sync', [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'SYNCING');
        Bus::assertBatchCount(1);
    }

    public function test_starting_a_sync_reconciles_from_before_the_request_and_enumerates_nothing_inside_it(): void
    {
        Bus::fake();
        $this->freezeSecond();
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->delete();
        $headers = $this->ownerHeaders();
        $realRepository = app(CrmProjectionRepositoryInterface::class);
        $repository = Mockery::mock(CrmProjectionRepositoryInterface::class);
        $repository->shouldReceive('hasSyncSources')->andReturnUsing(static fn (int $organizerId) => $realRepository->hasSyncSources($organizerId));
        $repository->shouldNotReceive('findOrderIdsForEvent');
        $repository->shouldNotReceive('findWaitlistEntryIdsForEvent');
        $this->app->instance(CrmProjectionRepositoryInterface::class, $repository);

        $this->crm('POST', 'sync', [], $headers)->assertOk()->assertJsonPath('data.status', 'SYNCING');

        $this->assertSame(
            now()->subSeconds(30)->toDateTimeString(),
            DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->value('reconciled_until'),
        );
    }

    public function test_starting_a_sync_with_nothing_to_project_is_ready_immediately(): void
    {
        Bus::fake();
        DB::table('crm_organizer_states')->where('organizer_id', $this->otherOrganizerId)->delete();

        $response = $this->crm('POST', 'sync', [], $this->ownerHeaders(), $this->otherOrganizerId)
            ->assertOk()
            ->assertJsonPath('data.status', 'READY')
            ->assertJsonPath('data.progress_percent', 100)
            ->assertJsonPath('data.contacts_count', 1);

        $this->assertNotNull($response->json('data.synced_at'));
        Bus::assertNothingBatched();
    }

    public function test_an_account_without_the_crm_flag_can_read_and_remove_audience_data_but_not_change_it(): void
    {
        Bus::fake();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, false);
        $headers = fn () => $this->ownerHeaders();
        $removals = ['POST crm/contacts/{contact_id}/unsubscribe', 'POST crm/campaigns/{campaign_id}/cancel', 'POST crm/campaigns/{campaign_id}/end-early-access', 'POST crm/contacts/bulk/unsubscribe', 'POST crm/contacts/bulk/delete', 'POST crm/automations/{automation_id}/disable'];
        $before = $this->crmState();

        foreach ($this->crmRoutes() as $name => [$method, $path, $body]) {
            if ($method === 'DELETE' || in_array($name, $removals, true)) {
                continue;
            }

            $response = $this->crm($method, $path, $body, $headers());

            if ($method === 'GET' || in_array($name, ['POST crm/contacts/export', 'POST crm/suppressions/lookup'], true)) {
                $this->assertSame(200, $response->getStatusCode(), $name);

                continue;
            }

            $this->assertSame(403, $response->getStatusCode(), $name);
            $this->assertSame('FEATURE_UNAVAILABLE', $response->json('error_code'), $name);
        }

        $this->assertSame($before, $this->crmState());
        Bus::assertNothingBatched();
        Bus::assertNothingDispatched();

        $scheduledId = $this->insertCampaign(['status' => 'SCHEDULED', 'scheduled_at' => now()->addDay()]);
        $this->crm('POST', "campaigns/$scheduledId/cancel", [], $headers())->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->crm('DELETE', "campaigns/$scheduledId", [], $headers())->assertNoContent();
        $this->crm('POST', "contacts/{$this->contactId}/unsubscribe", [], $headers())->assertOk();
        $this->crm('DELETE', "contacts/{$this->contactId}/notes/{$this->noteId}", [], $headers())->assertNoContent();
        $this->crm('DELETE', "tags/{$this->tagId}", [], $headers())->assertNoContent();
        $this->crm('DELETE', "segments/{$this->segmentId}", [], $headers())->assertNoContent();
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $headers())->assertNoContent();
    }

    public function test_a_tag_used_in_rules_cannot_be_deleted_and_forms_stop_applying_a_deleted_tag(): void
    {
        $tagId = $this->insertTag('Backstage');
        $keptTagId = $this->insertTag('Press list');
        $rule = json_encode(['match' => 'all', 'rules' => [['field' => 'tag', 'operator' => 'in', 'value' => [$tagId]]]]);
        $segmentId = DB::table('crm_segments')->insertGetId(['account_id' => $this->accountId, 'organizer_id' => $this->organizerId, 'name' => 'Backstage crew', 'definition' => $rule, 'created_at' => now(), 'updated_at' => now()]);
        $formId = DB::table('crm_signup_forms')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'public_id' => 'frm_'.uniqid(),
            'name' => 'Newsletter',
            'title' => 'Join',
            'button_label' => 'Join',
            'tag_ids' => json_encode([$tagId, $keptTagId]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->crm('DELETE', "tags/$tagId", [], $this->ownerHeaders())
            ->assertStatus(409);
        $this->assertStringContainsString('Backstage crew', $this->crm('DELETE', "tags/$tagId", [], $this->ownerHeaders())->json('message'));

        DB::table('crm_segments')->where('id', $segmentId)->delete();
        $this->crm('DELETE', "tags/$tagId", [], $this->ownerHeaders())->assertNoContent();

        $this->assertSame([$keptTagId], json_decode((string) DB::table('crm_signup_forms')->where('id', $formId)->value('tag_ids'), true));
    }

    #[DataProvider('campaignsHoldingATagRule')]
    public function test_a_tag_in_the_rules_of_a_campaign_that_has_not_been_sent_cannot_be_deleted(string $status, bool $blocksDeletion): void
    {
        $tagId = $this->insertTag('Backstage');
        $rule = json_encode(['match' => 'all', 'rules' => [['field' => 'tag', 'operator' => 'in', 'value' => [$tagId]]]]);
        $this->insertCampaign(['name' => 'Backstage invite', 'status' => $status, 'segment_definition' => $rule]);
        $this->insertCampaign(['name' => 'Elsewhere', 'status' => 'DRAFT', 'segment_definition' => $rule], $this->otherOrganizerId);

        $response = $this->crm('DELETE', "tags/$tagId", [], $this->ownerHeaders())->assertStatus($blocksDeletion ? 409 : 204);

        $this->assertSame($blocksDeletion ? 1 : 0, DB::table('crm_tags')->where('id', $tagId)->count());
        if ($blocksDeletion) {
            $this->assertStringContainsString('Backstage invite', $response->json('message'));
            $this->assertStringNotContainsString('Elsewhere', $response->json('message'));
        }
    }

    public static function campaignsHoldingATagRule(): array
    {
        return [
            'draft' => ['DRAFT', true],
            'rejected' => ['REJECTED', true],
            'scheduled' => ['SCHEDULED', true],
            'sending' => ['SENDING', false],
            'sent' => ['SENT', false],
            'cancelled' => ['CANCELLED', false],
        ];
    }

    private function spendDefinition(): array
    {
        return ['match' => 'all', 'rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD']]];
    }

    private function addRestrictedAccountUserOnce(array $organizerIds = [], array $eventIds = []): User
    {
        return $this->restrictedMember ??= $this->addRestrictedAccountUser($this->accountId, organizerIds: $organizerIds, eventIds: $eventIds);
    }

    private function otherAccountHeaders(): array
    {
        if ($this->otherAccount === null) {
            $this->otherAccount = $this->createAccountOwner();
            $this->setFeatureFlagOverride($this->otherAccount[1], FeatureFlag::CRM, true);
        }

        return $this->headersFor(...$this->otherAccount);
    }
}
