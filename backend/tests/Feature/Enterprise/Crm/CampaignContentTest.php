<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Template\UpsertCampaignTemplateHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Helper\Url;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CampaignContentTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
    }

    public function test_templates_are_listed_with_the_starters_created_renamed_and_deleted(): void
    {
        $list = $this->crm('GET', 'templates', [], $this->ownerHeaders())->assertOk();
        $this->assertSame(['event_announcement', 'last_chance', 'thank_you', 'newsletter'], array_column($list->json('starters'), 'key'));
        $this->assertSame(['Spring template'], array_column($list->json('data'), 'name'));

        $id = $this->crm('POST', 'templates', ['name' => ' Monthly news ', 'subject' => 'News from {{ organizer_name }}', 'preheader' => 'What is new', 'body' => '<p>Hi {{ first_name }}</p><script>alert(1)</script>'], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Monthly news')
            ->assertJsonPath('data.preheader', 'What is new')
            ->assertJsonPath('data.body', '<p>Hi {{ first_name }}</p>')
            ->json('data.id');

        $this->crm('POST', 'templates', ['name' => 'MONTHLY NEWS', 'body' => '<p>Again</p>'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->crm('PUT', "templates/$id", ['name' => 'Monthly digest', 'body' => '<p>Hello</p>'], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.name', 'Monthly digest')
            ->assertJsonPath('data.subject', null);

        $this->crm('DELETE', "templates/$id", [], $this->ownerHeaders())->assertNoContent();
        $this->assertNotNull(DB::table('crm_campaign_templates')->where('id', $id)->value('deleted_at'));
        $this->crm('POST', 'templates', ['name' => 'Monthly digest', 'body' => '<p>Reused name</p>'], $this->ownerHeaders())->assertCreated();
    }

    public function test_templates_of_another_organizer_cannot_be_changed_or_deleted(): void
    {
        $theirs = DB::table('crm_campaign_templates')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->otherOrganizerId,
            'name' => 'Theirs',
            'body' => '<p>Theirs</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->crm('PUT', "templates/$theirs", ['name' => 'Mine now', 'body' => '<p>x</p>'], $this->ownerHeaders())->assertNotFound();
        $this->crm('DELETE', "templates/$theirs", [], $this->ownerHeaders())->assertNoContent();
        $this->assertSame('Theirs', DB::table('crm_campaign_templates')->where('id', $theirs)->whereNull('deleted_at')->value('name'));
    }

    public function test_creating_a_campaign_from_a_template_marks_it_used(): void
    {
        $this->crm('POST', 'campaigns', ['name' => 'From template', 'subject' => 'Hi', 'body' => '<p>Spring is here</p>', 'template_id' => $this->templateId], $this->ownerHeaders())->assertCreated();

        $this->assertNotNull(DB::table('crm_campaign_templates')->where('id', $this->templateId)->value('last_used_at'));
    }

    public function test_unknown_placeholders_are_rejected_with_a_suggestion_and_event_placeholders_need_an_event(): void
    {
        $this->crm('POST', 'campaigns', ['name' => 'Typo', 'subject' => 'Hi {{ fist_name }}', 'body' => '<p>Hello</p>'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('errors.subject.0', '{{ fist_name }} isn\'t a placeholder we know. Did you mean {{ first_name }}?');

        $this->crm('POST', 'campaigns', ['name' => 'Event', 'subject' => 'Hi', 'body' => '<p>See you at {{ event_title }}</p>'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('errors.body.0', '{{ event_title }} only works when an event is linked.');

        $this->crm('POST', 'campaigns', ['name' => 'Event', 'subject' => 'Hi {{ first_name | default: "there" }}', 'body' => '<p>See you at {{ event_title }}, {{ last_event_title }}</p>', 'event_id' => $this->eventId], $this->ownerHeaders())
            ->assertCreated();

        $this->crm('POST', 'automations', ['name' => 'Welcome', 'trigger' => 'WELCOME', 'subject' => 'Welcome', 'body' => '<p>{{ event_title }}</p>'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_templates_reject_event_placeholders_because_they_have_no_event(): void
    {
        $this->crm('POST', 'templates', ['name' => 'Event news', 'subject' => 'Coming up: {{ event_title }}', 'body' => '<p>Hi</p>'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('errors.subject.0', 'Templates can\'t use event placeholders such as {{ event_title }}. Remove it here, then add it to the campaign you start from this template.');
        $this->crm('PUT', "templates/{$this->templateId}", ['name' => 'Saved', 'body' => '<p>On {{ event_date }}</p>'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('errors.body.0', 'Templates can\'t use event placeholders such as {{ event_date }}. Remove it here, then add it to the campaign you start from this template.');
        $this->crm('PUT', "campaigns/{$this->campaignId}", ['name' => 'News', 'subject' => 'Coming up: {{ event_title }}', 'body' => '<p>Hi</p>'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('errors.subject.0', '{{ event_title }} only works when an event is linked.');
        $this->crm('POST', 'templates', ['name' => 'Recap', 'body' => '<p>Thanks for coming to {{ last_event_title | default: "our event" }}, from {{ organizer_name }}</p>'], $this->ownerHeaders())
            ->assertCreated();
    }

    public function test_images_hosted_elsewhere_are_removed_on_save_and_uploaded_ones_kept(): void
    {
        $own = Url::getCdnUrl('event_cover/photo.jpg');
        $body = '<p>Look</p><img src="'.$own.'" alt="Stage"><img src="https://tracker.example.com/pixel.gif" alt="">';

        $saved = $this->crm('POST', 'campaigns', ['name' => 'Images', 'subject' => 'Hi', 'body' => $body], $this->ownerHeaders())
            ->assertCreated()
            ->json('data.body');

        $this->assertStringContainsString($own, $saved);
        $this->assertStringNotContainsString('tracker.example.com', $saved);
    }

    public function test_css_background_images_are_removed_on_save_and_other_styles_kept(): void
    {
        $body = '<p style="background-image:url(https://tracker.example.com/a.gif);color:red">a</p>'
            .'<p style="background:url(https://tracker.example.com/b.gif) no-repeat">b</p>'
            .'<table background="https://tracker.example.com/c.gif"><tr><td style="background-color:#ffff00">c</td></tr></table>';

        $saved = $this->crm('POST', 'campaigns', ['name' => 'Backgrounds', 'subject' => 'Hi', 'body' => $body], $this->ownerHeaders())
            ->assertCreated()
            ->json('data.body');

        $this->assertStringNotContainsString('tracker.example.com', $saved);
        $this->assertStringContainsString('color:#FF0000', $saved);
        $this->assertStringContainsString('background-color:#ffff00', $saved);
    }

    public function test_a_member_who_cannot_see_spend_gets_a_copy_without_the_originals_spend_audience(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $spendRules = ['match' => 'all', 'rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD']]];
        $spendSegmentId = $this->insertSegment($this->organizerId, 'Big spenders', $spendRules['rules']);
        $byRulesId = $this->insertCampaign(['name' => 'By rules', 'segment_definition' => json_encode($spendRules)]);
        $bySegmentId = $this->insertCampaign(['name' => 'By segment', 'segment_id' => $spendSegmentId]);

        foreach ([$byRulesId, $bySegmentId] as $originalId) {
            $copy = $this->crm('POST', "campaigns/$originalId/duplicate", [], $this->memberHeaders($member))
                ->assertCreated()
                ->assertJsonPath('data.segment_id', null)
                ->assertJsonPath('data.preset', null);
            $this->assertCount(1, $copy->json('warnings'));
            $this->assertStringContainsString('filters by spend', $copy->json('warnings.0'));
            $stored = DB::table('crm_campaigns')->where('id', $copy->json('data.id'))->first();
            $this->assertSame([null, null, null], [$stored->segment_id, $stored->preset_segment, $stored->segment_definition]);
        }

        $this->crm('POST', "campaigns/$bySegmentId/duplicate", [], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.segment_id', $spendSegmentId)
            ->assertJsonPath('warnings', []);
        $ownerCopyId = $this->crm('POST', "campaigns/$byRulesId/duplicate", [], $this->ownerHeaders())->assertCreated()->assertJsonPath('warnings', [])->json('data.id');
        $this->assertNotNull(DB::table('crm_campaigns')->where('id', $ownerCopyId)->value('segment_definition'));
    }

    public function test_a_campaign_is_duplicated_as_a_draft_with_its_content_audience_and_event(): void
    {
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update([
            'status' => 'SENT',
            'sent_at' => now()->subDay(),
            'segment_id' => $this->segmentId,
            'event_id' => $this->eventId,
            'early_access_product_ids' => json_encode([$this->productId]),
            'promo_code_id' => 999,
            'preheader' => 'Out now',
            'scheduled_at' => now()->subDay(),
        ]);

        $copy = $this->crm('POST', "campaigns/{$this->campaignId}/duplicate", [], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Copy of Spring news')
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.segment_id', $this->segmentId)
            ->assertJsonPath('data.event_id', $this->eventId)
            ->assertJsonPath('data.early_access_product_ids', [$this->productId])
            ->assertJsonPath('data.preheader', 'Out now')
            ->assertJsonPath('data.promo_code', null)
            ->assertJsonPath('data.scheduled_at', null)
            ->assertJsonPath('warnings', []);

        $this->assertSame(0, DB::table('crm_campaign_recipients')->where('campaign_id', $copy->json('data.id'))->count());
    }

    public function test_a_duplicate_drops_what_no_longer_exists_and_never_copies_an_automation_audience(): void
    {
        $deletedSegment = $this->insertSegment($this->organizerId, 'Gone', [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]);
        DB::table('crm_segments')->where('id', $deletedSegment)->delete();
        $goneEvent = $this->createEvent($this->accountId, $this->organizerId, 'Gone event');
        DB::table('events')->where('id', $goneEvent)->update(['deleted_at' => now()]);
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['segment_id' => $deletedSegment, 'event_id' => $goneEvent]);

        $response = $this->crm('POST', "campaigns/{$this->campaignId}/duplicate", [], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.segment_id', null)
            ->assertJsonPath('data.event_id', null);
        $this->assertCount(2, $response->json('warnings'));

        $automationCampaign = $this->insertCampaign(['automation_id' => $this->automationId, 'preset_segment' => 'first_timers', 'status' => 'SENT']);
        $this->crm('POST', "campaigns/$automationCampaign/duplicate", [], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.preset', null)
            ->assertJsonPath('data.automation_id', null)
            ->assertJsonCount(1, 'warnings');

        $this->crm('POST', "campaigns/{$this->otherCampaignId}/duplicate", [], $this->ownerHeaders())->assertNotFound();
    }

    public function test_an_organizer_can_keep_at_most_the_maximum_number_of_templates(): void
    {
        $now = now();
        DB::table('crm_campaign_templates')->insert(array_map(fn (int $index) => [
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => "Template $index",
            'body' => '<p>Hi</p>',
            'created_at' => $now,
            'updated_at' => $now,
        ], range(1, UpsertCampaignTemplateHandler::MAX_TEMPLATES_PER_ORGANIZER - 2)));
        $count = fn () => DB::table('crm_campaign_templates')->where('organizer_id', $this->organizerId)->whereNull('deleted_at')->count();

        $lastId = $this->crm('POST', 'templates', ['name' => 'The last one', 'body' => '<p>Hi</p>'], $this->ownerHeaders())->assertCreated()->json('data.id');
        $this->assertSame(UpsertCampaignTemplateHandler::MAX_TEMPLATES_PER_ORGANIZER, $count());

        $this->crm('POST', 'templates', ['name' => 'One too many', 'body' => '<p>Hi</p>'], $this->ownerHeaders())->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->assertSame(UpsertCampaignTemplateHandler::MAX_TEMPLATES_PER_ORGANIZER, $count());
        $this->crm('PUT', "templates/$lastId", ['name' => 'Still editable', 'body' => '<p>Hello</p>'], $this->ownerHeaders())->assertOk();
        $this->crm('POST', 'templates', ['name' => 'Elsewhere', 'body' => '<p>Hi</p>'], $this->ownerHeaders(), $this->otherOrganizerId)->assertCreated();

        $this->crm('DELETE', "templates/$lastId", [], $this->ownerHeaders())->assertNoContent();
        $this->crm('POST', 'templates', ['name' => 'Room again', 'body' => '<p>Hi</p>'], $this->ownerHeaders())->assertCreated();
    }

    public function test_a_duplicate_of_a_rejected_campaign_starts_clean_of_its_review_and_send_history(): void
    {
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update([
            'status' => 'REJECTED',
            'status_reason' => 'REJECTED_BY_REVIEW',
            'review_note' => 'Looks like spam',
            'approved_at' => now()->subDays(2),
            'launched_at' => now()->subDays(2),
            'sent_at' => now()->subDays(2),
            'recipients_count' => 40,
        ]);

        $copyId = $this->crm('POST', "campaigns/{$this->campaignId}/duplicate", [], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.status_reason', null)
            ->assertJsonPath('data.review_note', null)
            ->json('data.id');

        $copy = $this->campaign($copyId);
        $this->assertSame([null, null, null, null, null], [$copy->status_reason, $copy->review_note, $copy->approved_at, $copy->launched_at, $copy->sent_at]);
        $this->assertSame(0, (int) $copy->recipients_count);
        $this->assertSame('Looks like spam', $this->campaign($this->campaignId)->review_note);
    }

    public function test_a_campaign_can_still_be_read_and_listed_after_its_segment_or_automation_is_deleted(): void
    {
        $segmentId = $this->insertSegment($this->organizerId, 'Gone', [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]);
        $bySegmentId = $this->insertCampaign(['name' => 'By segment', 'status' => 'SENT', 'sent_at' => now()->subDay(), 'segment_id' => $segmentId]);
        $this->insertRecipient($bySegmentId, $this->contactId, 'ada@example.com', ['status' => 'SENT']);
        $byAutomationId = $this->insertCampaign(['name' => 'By automation', 'status' => 'SENT', 'sent_at' => now()->subDay(), 'automation_id' => $this->automationId, 'automation_run_key' => 'date:2026-11-01']);
        $this->crm('DELETE', "segments/$segmentId", [], $this->ownerHeaders())->assertNoContent();
        $this->crm('DELETE', "automations/{$this->automationId}", [], $this->ownerHeaders())->assertNoContent();

        $this->crm('GET', "campaigns/$bySegmentId", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.segment_name', null)
            ->assertJsonPath('data.stats.sent', 1);
        $this->crm('GET', "campaigns/$byAutomationId", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.name', 'By automation');
        $this->crm('GET', "campaigns/$bySegmentId/recipients", [], $this->ownerHeaders())->assertOk()->assertJsonPath('meta.total', 1);
        $this->crm('GET', "campaigns/$bySegmentId/preview", [], $this->ownerHeaders())->assertOk();

        $listed = array_column($this->crm('GET', 'campaigns?per_page=100', [], $this->ownerHeaders())->assertOk()->json('data'), 'id');
        $this->assertContains($bySegmentId, $listed);
        $automated = array_column($this->crm('GET', 'campaigns?folder=automated&per_page=100', [], $this->ownerHeaders())->assertOk()->json('data'), 'id');
        $this->assertContains($byAutomationId, [...$listed, ...$automated]);
    }

    public function test_the_preview_can_be_rendered_as_a_contact_of_the_organizer_only(): void
    {
        DB::table('crm_contacts')->where('id', $this->contactId)->update(['first_name' => 'Ada']);
        DB::table('crm_contact_activities')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $this->contactId,
            'type' => 'CHECKED_IN',
            'source_type' => 'CHECK_IN',
            'source_id' => 1,
            'event_id' => $this->eventId,
            'event_occurrence_id' => $this->occurrenceId,
            'occurred_at' => now()->subDay(),
        ]);
        $body = ['subject' => 'Hi {{ first_name | default: "there" }}', 'body' => '<p>Thanks for coming to {{ last_event_title | default: "our event" }}</p>'];

        $preview = $this->crm('POST', 'campaigns/preview', [...$body, 'contact_id' => $this->contactId], $this->ownerHeaders())->assertOk();
        $this->assertSame('Hi Ada', $preview->json('data.subject'));
        $this->assertStringContainsString('Thanks for coming to Main Event', $preview->json('data.html'));

        $asViewer = $this->crm('POST', 'campaigns/preview', $body, $this->ownerHeaders())->assertOk();
        $this->assertStringContainsString('Thanks for coming to our event', $asViewer->json('data.html'));

        $this->crm('POST', 'campaigns/preview', [...$body, 'contact_id' => $this->otherContactId], $this->ownerHeaders())->assertNotFound();
    }

    public function test_a_linked_event_is_shown_as_a_card_with_its_next_date_and_venue(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE', 'timezone' => 'UTC']);
        $locationId = DB::table('locations')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'short_id' => 'loc_'.uniqid(),
            'name' => 'Dockside Hall',
            'structured_address' => json_encode(['city' => 'Dublin']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_locations')->insert(['event_id' => $this->eventId, 'location_id' => $locationId, 'short_id' => 'el_'.uniqid(), 'type' => 'IN_PERSON', 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->crm('POST', 'campaigns/preview', ['subject' => 'Hi', 'preheader' => 'Doors at 8', 'body' => '<p>Come</p>', 'event_id' => $this->eventId], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.preheader', 'Doors at 8')
            ->json('data.html');

        $this->assertStringContainsString('Main Event', $html);
        $this->assertStringContainsString('Dockside Hall, Dublin', $html);
        $this->assertStringContainsString('Get tickets', $html);
        $this->assertStringContainsString('Doors at 8', $html);
    }

    public function test_the_send_check_warns_about_images_without_a_description_and_an_unpublished_event(): void
    {
        $own = Url::getCdnUrl('event_cover/photo.jpg');
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update([
            'body' => '<p>Look</p><img src="'.$own.'" alt="">',
            'event_id' => $this->eventId,
            'early_access_product_ids' => null,
        ]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'DRAFT']);
        $this->verifyAccount(attributes: ['is_manually_verified' => true]);

        $warnings = $this->crm('GET', "campaigns/{$this->campaignId}/send-check", [], $this->ownerHeaders())->assertOk()->json('data.warnings');

        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('no description', $warnings[0]);
        $this->assertStringContainsString('isn\'t published', $warnings[1]);
    }

    public function test_a_campaign_with_an_unknown_placeholder_saved_before_validation_is_blocked_at_send(): void
    {
        DB::table('crm_campaigns')->where('id', $this->campaignId)->update(['body' => '<p>Hi {{ nickname }}</p>']);
        $this->verifyAccount(attributes: ['is_manually_verified' => true]);

        $this->crm('GET', "campaigns/{$this->campaignId}/send-check", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.blocked_reason', '{{ nickname }} isn\'t a placeholder we know. Use the Insert menu to add one.');
    }

    public function test_early_access_tickets_are_validated_hidden_only_once_approved_and_can_be_made_public_again(): void
    {
        Bus::fake([DispatchCampaignJob::class]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        [$otherEventProductId] = $this->insertTicket($this->otherEventId);

        $this->crm('POST', 'campaigns', ['name' => 'Early', 'subject' => 'Hi', 'body' => '<p>Hi</p>', 'event_id' => $this->eventId, 'early_access_product_ids' => [$otherEventProductId]], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('early_access_product_ids');

        $campaignId = $this->crm('POST', 'campaigns', ['name' => 'Early', 'subject' => 'Hi', 'body' => '<p>Hi</p>', 'event_id' => $this->eventId, 'early_access_product_ids' => [$this->productId, $this->productId]], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.early_access_product_ids', [$this->productId])
            ->json('data.id');
        $this->assertFalse((bool) DB::table('products')->where('id', $this->productId)->value('is_hidden_without_promo_code'));

        $this->verifyAccount(attributes: ['is_manually_verified' => true]);
        $this->crm('GET', "campaigns/$campaignId/send-check", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.early_access_tickets.0.id', $this->productId)
            ->assertJsonPath('data.early_access_tickets.0.is_hidden', false);
        $this->crm('POST', "campaigns/$campaignId/end-early-access", [], $this->ownerHeaders())->assertStatus(409);

        config(['app.saas_mode_enabled' => true]);
        $this->assertSame('PENDING_REVIEW', app(CampaignLaunchService::class)->launch(app(CrmCampaignRepositoryInterface::class)->findById($campaignId))->value);
        $this->assertFalse((bool) DB::table('products')->where('id', $this->productId)->value('is_hidden_without_promo_code'));
        $this->assertNull(DB::table('crm_campaigns')->where('id', $campaignId)->value('promo_code_id'));

        $pending = app(CrmCampaignRepositoryInterface::class)->findById($campaignId);
        $this->assertTrue(app(CampaignLaunchService::class)->approve($pending, Carbon::parse($pending->getUpdatedAt())));
        $this->assertTrue((bool) DB::table('products')->where('id', $this->productId)->value('is_hidden_without_promo_code'));
        $this->assertNotNull(DB::table('crm_campaigns')->where('id', $campaignId)->value('promo_code_id'));

        $this->crm('POST', "campaigns/$campaignId/end-early-access", [], $this->ownerHeaders())->assertOk();
        $this->assertFalse((bool) DB::table('products')->where('id', $this->productId)->value('is_hidden_without_promo_code'));
    }
}
