<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignReportHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    private const FOLDER_OF_STATUS = [
        'DRAFT' => 'drafts',
        'REJECTED' => 'drafts',
        'SCHEDULED' => 'scheduled',
        'PENDING_REVIEW' => 'scheduled',
        'SENDING' => 'sent',
        'SENT' => 'sent',
        'PAUSED' => 'sent',
        'CANCELLED' => 'sent',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => false]);
        $this->setUpCrmApi();
    }

    public function test_every_status_belongs_to_exactly_one_folder(): void
    {
        $this->assertEqualsCanonicalizing(CrmCampaignStatus::valuesArray(), array_keys(self::FOLDER_OF_STATUS));
        DB::table('crm_campaigns')->where('id', $this->campaignId)->delete();
        $idsByFolder = ['drafts' => [], 'scheduled' => [], 'sent' => []];
        foreach (self::FOLDER_OF_STATUS as $status => $folder) {
            $idsByFolder[$folder][] = $this->insertCampaign(['status' => $status, 'name' => $status]);
        }
        $this->insertCampaign(['status' => 'SENT'], $this->otherOrganizerId);

        foreach ($idsByFolder as $folder => $ids) {
            $response = $this->crm('GET', "campaigns?folder=$folder", [], $this->ownerHeaders())->assertOk();

            $this->assertSame(array_reverse($ids), array_column($response->json('data'), 'id'), $folder);
            $this->assertSame(count($ids), $response->json('meta.total'), $folder);
            $this->assertSame(['drafts' => 2, 'scheduled' => 2, 'sent' => 4, 'automated' => 0], $response->json('meta.folders'), $folder);
        }
    }

    public function test_delivery_numbers_read_before_a_campaign_was_sent_are_not_shown_once_it_has_been(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'DRAFT']);
        $recipientId = $this->insertRecipient($campaignId, $this->contactId, 'ada@example.com');

        $this->crm('GET', "campaigns/$campaignId", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.stats.pending', 1)
            ->assertJsonPath('data.stats.sent', 0);

        DB::table('crm_campaign_recipients')->where('id', $recipientId)->update(['status' => 'SENT', 'sent_at' => now()]);
        DB::table('crm_campaigns')->where('id', $campaignId)->update(['status' => 'SENT', 'sent_at' => now()]);

        $this->crm('GET', "campaigns/$campaignId", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.stats.pending', 0)
            ->assertJsonPath('data.stats.sent', 1);
        $listed = collect($this->crm('GET', 'campaigns?folder=sent', [], $this->ownerHeaders())->assertOk()->json('data'))->firstWhere('id', $campaignId);
        $this->assertSame([0, 1], [$listed['stats']['pending'], $listed['stats']['sent']]);
    }

    public function test_without_a_known_folder_every_campaign_is_listed_with_the_folder_counts(): void
    {
        $sentId = $this->insertCampaign(['status' => 'SENT']);

        foreach (['campaigns', 'campaigns?folder=', 'campaigns?folder=archive', 'campaigns?folder[]=sent'] as $path) {
            $response = $this->crm('GET', $path, [], $this->ownerHeaders())->assertOk();

            $this->assertSame([$sentId, $this->campaignId], array_column($response->json('data'), 'id'), $path);
            $this->assertSame(['drafts' => 1, 'scheduled' => 0, 'sent' => 1, 'automated' => 0], $response->json('meta.folders'), $path);
        }
    }

    public function test_an_organizer_without_campaigns_has_zeroed_folders(): void
    {
        DB::table('crm_campaigns')->where('organizer_id', $this->organizerId)->delete();

        $this->crm('GET', 'campaigns?folder=sent', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.folders', ['drafts' => 0, 'scheduled' => 0, 'sent' => 0, 'automated' => 0]);
    }

    public function test_folders_page_within_the_folder(): void
    {
        $firstId = $this->insertCampaign(['status' => 'SENT']);
        $secondId = $this->insertCampaign(['status' => 'PAUSED']);

        $this->crm('GET', 'campaigns?folder=sent&per_page=1&page=2', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstId)
            ->assertJsonPath('meta.total', 2);
        $this->crm('GET', 'campaigns?folder=sent&per_page=1', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', $secondId);
    }

    public function test_cancelled_automation_campaigns_are_hidden_from_lists_and_counts(): void
    {
        $automationId = $this->insertAutomationRow();
        $skippedId = $this->insertCampaign(['status' => 'CANCELLED', 'status_reason' => 'AUTOMATION_SKIPPED', 'automation_id' => $automationId, 'automation_run_key' => 'event:1']);
        $automatedSentId = $this->insertCampaign(['status' => 'SENT', 'automation_id' => $automationId, 'automation_run_key' => 'event:2']);
        $automatedPendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'automation_id' => $automationId, 'automation_run_key' => 'event:3']);
        $manualCancelledId = $this->insertCampaign(['status' => 'CANCELLED']);

        $all = $this->crm('GET', 'campaigns', [], $this->ownerHeaders())->assertOk();
        $sent = $this->crm('GET', 'campaigns?folder=sent', [], $this->ownerHeaders())->assertOk();

        $this->assertSame([$manualCancelledId, $automatedPendingId, $automatedSentId, $this->campaignId], array_column($all->json('data'), 'id'));
        $this->assertSame([$manualCancelledId, $automatedSentId], array_column($sent->json('data'), 'id'));
        $this->assertNotContains($skippedId, array_column($all->json('data'), 'id'));
        $this->assertSame(['drafts' => 1, 'scheduled' => 1, 'sent' => 2, 'automated' => 2], $all->json('meta.folders'));
        $this->assertSame(4, $all->json('meta.total'));
        $this->assertSame($automationId, collect($all->json('data'))->firstWhere('id', $automatedSentId)['automation_id']);
        $this->assertNull(collect($all->json('data'))->firstWhere('id', $manualCancelledId)['automation_id']);
    }

    public function test_the_recipients_report_joins_each_recipient_to_its_delivery(): void
    {
        [$campaignId, $recipientIds] = $this->seedReport();

        $response = $this->crm('GET', "campaigns/$campaignId/recipients", [], $this->ownerHeaders())->assertOk();

        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertSame(array_values($recipientIds), array_column($response->json('data'), 'id'));
        $this->assertSame(count($recipientIds), $response->json('meta.total'));
        $this->assertSame(
            [
                'id' => $recipientIds['clicked'],
                'contact_id' => $this->contactId,
                'email' => 'ada@example.com',
                'full_name' => 'Ada Lovelace',
                'status' => 'SENT',
            ],
            array_intersect_key($rows[$recipientIds['clicked']], array_flip(['id', 'contact_id', 'email', 'full_name', 'status'])),
        );
        foreach (['sent_at', 'delivered_at', 'opened_at', 'clicked_at'] as $field) {
            $this->assertNotNull($rows[$recipientIds['clicked']][$field], $field);
        }
        $this->assertSame([null, null], [$rows[$recipientIds['clicked']]['bounced_at'], $rows[$recipientIds['clicked']]['complained_at']]);

        $unmatched = $rows[$recipientIds['without_message']];
        $this->assertSame(
            ['SENT', null, null, null, null, null],
            [$unmatched['status'], $unmatched['delivered_at'], $unmatched['opened_at'], $unmatched['clicked_at'], $unmatched['bounced_at'], $unmatched['complained_at']],
        );
        $this->assertSame('', $rows[$recipientIds['pending']]['full_name']);
        $this->assertSame(
            ['PROVIDER_UNAVAILABLE', 'UNSUBSCRIBED', null, null],
            array_map(static fn (string $name) => $rows[$recipientIds[$name]]['failure_reason'], ['failed', 'skipped', 'pending', 'clicked']),
        );
        $this->assertNotNull($rows[$recipientIds['bounced']]['bounced_at']);
        $this->assertNotNull($rows[$recipientIds['complained']]['complained_at']);
    }

    public static function recipientFilters(): array
    {
        return [
            'delivered' => ['delivered', ['clicked', 'opened', 'delivered', 'complained']],
            'opened' => ['opened', ['clicked', 'opened']],
            'clicked' => ['clicked', ['clicked']],
            'bounced includes complaints' => ['bounced', ['bounced', 'complained']],
            'not sent' => ['not_sent', ['failed', 'skipped']],
            'unknown filter lists everyone' => ['everything', ['clicked', 'opened', 'delivered', 'bounced', 'complained', 'without_message', 'failed', 'skipped', 'pending']],
        ];
    }

    #[DataProvider('recipientFilters')]
    public function test_the_recipients_report_filters_by_delivery_outcome(string $filter, array $expected): void
    {
        [$campaignId, $recipientIds] = $this->seedReport();

        $response = $this->crm('GET', "campaigns/$campaignId/recipients?filter=$filter", [], $this->ownerHeaders())->assertOk();

        $this->assertSame(
            array_map(static fn (string $name) => $recipientIds[$name], $expected),
            array_column($response->json('data'), 'id'),
        );
        $this->assertSame(count($expected), $response->json('meta.total'));
    }

    public function test_the_recipients_report_pages_fifty_at_a_time(): void
    {
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour()]);
        $now = now();
        DB::table('crm_campaign_recipients')->insert(array_map(fn (int $index) => [
            'campaign_id' => $campaignId,
            'organizer_id' => $this->organizerId,
            'email' => sprintf('recipient%03d@example.com', $index),
            'status' => $index === 51 ? 'FAILED' : 'SENT',
            'created_at' => $now,
            'updated_at' => $now,
        ], range(1, 51)));

        $first = $this->crm('GET', "campaigns/$campaignId/recipients", [], $this->ownerHeaders())->assertOk();
        $second = $this->crm('GET', "campaigns/$campaignId/recipients?page=2", [], $this->ownerHeaders())->assertOk();
        $filtered = $this->crm('GET', "campaigns/$campaignId/recipients?filter=not_sent&page=1", [], $this->ownerHeaders())->assertOk();

        $this->assertSame([50, 51, 50, 2], [count($first->json('data')), $first->json('meta.total'), $first->json('meta.per_page'), $first->json('meta.last_page')]);
        $this->assertCount(1, $second->json('data'));
        $this->assertSame($second->json('data.0.id'), $filtered->json('data.0.id'));
        $this->assertSame(1, $filtered->json('meta.total'));
        $this->crm('GET', "campaigns/$campaignId/recipients?page=0", [], $this->ownerHeaders())->assertOk()->assertJsonCount(50, 'data');
        $this->crm('GET', "campaigns/$campaignId/recipients?page=nope&filter[]=x", [], $this->ownerHeaders())->assertOk()->assertJsonCount(50, 'data');
    }

    public function test_the_recipients_report_hides_the_email_of_a_deleted_contact(): void
    {
        [$campaignId, $recipientIds] = $this->seedReport();

        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();

        $response = $this->crm('GET', "campaigns/$campaignId/recipients", [], $this->ownerHeaders())->assertOk();
        $deleted = collect($response->json('data'))->firstWhere('id', $recipientIds['clicked']);
        $this->assertSame([null, null, '', 'SENT'], [$deleted['contact_id'], $deleted['email'], $deleted['full_name'], $deleted['status']]);
        $this->assertNotNull($deleted['clicked_at']);
        $this->assertStringNotContainsString('ada@example.com', (string) $response->getContent());
        $this->assertStringNotContainsString('deleted:', (string) $response->getContent());
        $this->assertSame('second@example.com', collect($response->json('data'))->firstWhere('id', $recipientIds['opened'])['email']);
    }

    public function test_the_recipients_of_another_organizers_campaign_are_not_found(): void
    {
        $this->insertRecipient($this->otherCampaignId, $this->otherContactId, 'other@example.com', ['status' => 'SENT'], $this->otherOrganizerId);

        $this->crm('GET', "campaigns/{$this->otherCampaignId}/recipients", [], $this->ownerHeaders())->assertNotFound();
        $this->crm('GET', 'campaigns/999999999/recipients', [], $this->ownerHeaders())->assertNotFound();
        $this->crm('GET', "campaigns/{$this->otherCampaignId}/recipients", [], $this->ownerHeaders(), $this->otherOrganizerId)
            ->assertOk()
            ->assertJsonPath('data.0.email', 'other@example.com');
    }

    public function test_recipients_of_other_campaigns_never_leak_into_the_report(): void
    {
        [$campaignId, $recipientIds] = $this->seedReport();
        $otherId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subHour()]);
        $this->insertRecipient($otherId, $this->contactId, 'ada@example.com', ['status' => 'SENT', 'provider_message_id' => 'elsewhere']);
        $this->insertDelivery('elsewhere', ['delivered_at' => now(), 'first_clicked_at' => now()]);

        $this->crm('GET', "campaigns/$campaignId/recipients?filter=clicked", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $recipientIds['clicked']);
    }

    public function test_a_draft_preview_renders_the_unsaved_message_and_changes_nothing(): void
    {
        Bus::fake();
        $before = $this->crmState();

        $html = $this->crm('POST', 'campaigns/preview', [
            'subject' => 'Unsaved subject',
            'body' => '<p>Unsaved <strong>draft</strong></p><script>alert("x")</script><img src="x" onerror="alert(2)">',
        ], $this->ownerHeaders())->assertOk()->json('data.html');

        $this->assertMatchesRegularExpression('/Unsaved <strong[^>]*>draft<\/strong>/', $html);
        $this->assertStringContainsString('Test Organizer', $html);
        $this->assertStringContainsString('Recipients will see an unsubscribe link here.', $html);
        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringNotContainsString('alert(', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('unsubscribe/', $html);
        $this->assertSame($before, $this->crmState());
        $this->assertCount(0, app('mail.manager')->mailer()->getSymfonyTransport()->messages());
        Bus::assertNothingDispatched();
    }

    public static function scriptedDrafts(): array
    {
        return [
            'plain script' => ['<script>alert(1)</script><p>Safe</p>'],
            'mixed case script' => ['<ScRiPt src="//evil.test/x.js"></sCrIpT><p>Safe</p>'],
            'script split across lines' => ["<p>Safe</p>\n\n<script>\n\nalert(1)\n\n</script>"],
            'nested script' => ['<scr<script>ipt>alert(1)</scr</script>ipt><p>Safe</p>'],
            'svg script' => ['<svg><script>alert(1)</script></svg><p>Safe</p>'],
            'script in the subject only' => ['<p>Safe</p>', '<script>alert(1)</script>'],
        ];
    }

    #[DataProvider('scriptedDrafts')]
    public function test_script_tags_never_appear_in_a_draft_preview(string $body, string $subject = 'Hello'): void
    {
        $html = $this->crm('POST', 'campaigns/preview', ['subject' => $subject, 'body' => $body], $this->ownerHeaders())->assertOk()->json('data.html');

        $this->assertStringContainsString('Safe', $html);
        $this->assertDoesNotMatchRegularExpression('/<\s*script/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<\s*\/\s*script/i', $html);
    }

    public function test_a_draft_preview_links_to_an_event_of_the_organizer_and_ignores_anyone_elses(): void
    {
        [, $foreignAccountId] = $this->createAccountOwner();
        $foreignEventId = $this->createEvent($foreignAccountId, $this->createOrganizer($foreignAccountId), 'Foreign Account Event');
        DB::table('events')->whereIn('id', [$this->eventId, $this->otherEventId, $foreignEventId])->update(['status' => 'LIVE']);
        $preview = fn (array $body) => $this->crm('POST', 'campaigns/preview', ['subject' => 'Hi', 'body' => '<p>Come along</p>', ...$body], $this->ownerHeaders())->assertOk()->json('data.html');

        $own = $preview(['event_id' => $this->eventId, 'early_access_product_ids' => [$this->productId]]);
        $this->assertStringContainsString('/event/'.$this->eventId.'/main-event?promo_code=early-preview', $own);
        $this->assertStringContainsString('Get early access', $own);
        $this->assertStringNotContainsString('Get tickets', $own);

        $card = $preview(['event_id' => $this->eventId]);
        $this->assertStringContainsString('/event/'.$this->eventId.'/main-event"', $card);
        $this->assertStringContainsString('Get tickets', $card);

        foreach ([$this->otherEventId, $foreignEventId, 999999999] as $eventId) {
            $html = $preview(['event_id' => $eventId, 'early_access_product_ids' => [$this->productId]]);
            $this->assertStringContainsString('Come along', $html);
            $this->assertStringNotContainsString('/event/', $html);
            $this->assertStringNotContainsString('Get early access', $html);
        }

        $this->assertStringNotContainsString('/event/', $preview([]));
        $this->assertStringNotContainsString('/event/', $preview(['event_id' => null]));
    }

    public function test_a_draft_preview_accepts_an_empty_draft_and_rejects_oversized_or_malformed_input(): void
    {
        $this->crm('POST', 'campaigns/preview', [], $this->ownerHeaders())->assertOk()->assertJsonStructure(['data' => ['html']]);
        $this->crm('POST', 'campaigns/preview', ['subject' => null, 'body' => null], $this->ownerHeaders())->assertOk();

        $this->crm('POST', 'campaigns/preview', ['body' => str_repeat('a', 100001)], $this->ownerHeaders())->assertStatus(422)->assertJsonValidationErrors(['body']);
        $this->crm('POST', 'campaigns/preview', ['subject' => str_repeat('a', 201)], $this->ownerHeaders())->assertStatus(422)->assertJsonValidationErrors(['subject']);
        $this->crm('POST', 'campaigns/preview', ['body' => ['<p>x</p>']], $this->ownerHeaders())->assertStatus(422)->assertJsonValidationErrors(['body']);
        $this->crm('POST', 'campaigns/preview', ['event_id' => 'abc'], $this->ownerHeaders())->assertStatus(422)->assertJsonValidationErrors(['event_id']);
        $this->crm('POST', 'campaigns/preview', ['event_id' => 0], $this->ownerHeaders())->assertStatus(422)->assertJsonValidationErrors(['event_id']);
    }

    public function test_markdown_in_a_draft_preview_is_not_turned_into_a_link(): void
    {
        $html = $this->crm('POST', 'campaigns/preview', [
            'body' => "<p>Hello</p>\n\n[click me](//evil.test/phish)\n\n![pixel](//evil.test/pixel.png)",
        ], $this->ownerHeaders())->assertOk()->json('data.html');

        $this->assertStringContainsString('Hello', $html);
        $this->assertDoesNotMatchRegularExpression('/(href|src)\s*=\s*["\']?[^"\'>]*evil\.test/i', $html);
    }

    public function test_a_draft_preview_needs_edit_access_and_the_crm_flag(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $body = ['subject' => 'Hi', 'body' => '<p>Hello</p>'];

        $this->crm('POST', 'campaigns/preview', $body, $this->memberHeaders($viewer))->assertForbidden()->assertJsonPath('error_code', 'READ_ONLY_ROLE');
        $this->crm('POST', 'campaigns/preview', $body, $this->ownerHeaders(), $this->otherOrganizerId)->assertOk();

        $this->useLicenceKey(null);
        $this->crm('POST', 'campaigns/preview', $body, $this->ownerHeaders())->assertForbidden()->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');
    }

    /**
     * @return array{0: int, 1: array<string, int>}
     */
    private function seedReport(): array
    {
        $sentAt = now()->subHours(3)->startOfSecond();
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => $sentAt]);
        $outcomes = [
            'clicked' => ['delivered_at' => $sentAt, 'first_opened_at' => $sentAt, 'first_clicked_at' => $sentAt],
            'opened' => ['delivered_at' => $sentAt, 'first_opened_at' => $sentAt],
            'delivered' => ['delivered_at' => $sentAt],
            'bounced' => ['bounced_at' => $sentAt, 'bounce_type' => 'PERMANENT'],
            'complained' => ['delivered_at' => $sentAt, 'complained_at' => $sentAt],
        ];
        $contactIds = ['clicked' => $this->contactId, 'opened' => $this->secondContactId];
        $emails = ['clicked' => 'ada@example.com', 'opened' => 'second@example.com'];
        $recipientIds = [];

        foreach ($outcomes as $name => $delivery) {
            $recipientIds[$name] = $this->insertRecipient(
                $campaignId,
                $contactIds[$name] ?? $this->insertContact("$name@example.com"),
                $emails[$name] ?? "$name@example.com",
                ['status' => 'SENT', 'provider_message_id' => "report-$name", 'sent_at' => $sentAt],
            );
            $this->insertDelivery("report-$name", $delivery);
        }

        $recipientIds['without_message'] = $this->insertRecipient($campaignId, $this->insertContact('without@example.com'), 'without@example.com', ['status' => 'SENT', 'sent_at' => $sentAt]);
        $recipientIds['failed'] = $this->insertRecipient($campaignId, $this->insertContact('failed@example.com'), 'failed@example.com', ['status' => 'FAILED', 'failure_reason' => 'PROVIDER_UNAVAILABLE']);
        $recipientIds['skipped'] = $this->insertRecipient($campaignId, $this->insertContact('skipped@example.com'), 'skipped@example.com', ['status' => 'SKIPPED', 'failure_reason' => 'UNSUBSCRIBED']);
        $recipientIds['pending'] = $this->insertRecipient($campaignId, $this->insertContact('pending@example.com'), 'pending@example.com');
        DB::table('email_deliveries')->insert(['provider_message_id' => null, 'delivered_at' => $sentAt, 'first_clicked_at' => $sentAt, 'bounced_at' => $sentAt, 'created_at' => now(), 'updated_at' => now()]);

        return [$campaignId, $recipientIds];
    }

    private function insertAutomationRow(): int
    {
        return DB::table('crm_automations')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'trigger' => 'EVENT_PUBLISHED',
            'is_enabled' => true,
            'enabled_at' => now()->subDay(),
            'subject' => 'Just announced',
            'body' => '<p>New event</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
