<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SuppressionTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    private const SUPPRESSED_DEFINITION = [
        'match' => 'all',
        'rules' => [['field' => 'email_marketing_status', 'operator' => 'in', 'value' => ['UNSUBSCRIBED', 'BOUNCED', 'COMPLAINED']]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
    }

    public function test_reactivating_a_bounce_restores_what_the_ledger_said_before_it_and_is_recorded(): void
    {
        $recipientId = $this->insertRecipient($this->campaignId, $this->contactId, 'ada@example.com', ['status' => 'SENT']);
        $this->insertLedger('ada@example.com', 'BOUNCED', 'CAMPAIGN', $recipientId, now()->subDay());
        $this->assertSame('BOUNCED', $this->marketingStatus($this->contactId));

        $emailId = $this->emailId('ada@example.com');
        $this->crm('POST', "contacts/{$this->contactId}/emails/$emailId/clear-bounce", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.email_marketing_status', 'SUBSCRIBED');

        $history = $this->crm('GET', "contacts/{$this->contactId}/consent", [], $this->ownerHeaders())->assertOk();
        $this->assertSame('BOUNCE_CLEARED', $history->json('data.0.action'));
        $this->assertSame(trim($this->owner->first_name.' '.$this->owner->last_name), $history->json('data.0.user_name'));

        $this->insertLedger('ada@example.com', 'BOUNCED', 'CAMPAIGN', $this->insertRecipient($this->insertCampaign(['name' => 'Summer news']), $this->contactId, 'ada@example.com'), now()->addMinute());
        $this->assertSame('BOUNCED', $this->marketingStatus($this->contactId));
    }

    public function test_reactivating_never_opts_anyone_in(): void
    {
        $this->insertLedger('second@example.com', 'BOUNCED', 'CAMPAIGN', null, now()->subDay());
        DB::table('crm_contacts')->where('id', $this->secondContactId)->update(['email_marketing_status' => 'BOUNCED']);

        $this->crm('POST', "contacts/{$this->secondContactId}/emails/{$this->emailId('second@example.com')}/clear-bounce", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.email_marketing_status', 'NOT_SUBSCRIBED');
    }

    public function test_only_bounces_can_be_reactivated_and_a_recent_provider_refusal_blocks_it(): void
    {
        $emailId = $this->emailId('ada@example.com');
        $path = "contacts/{$this->contactId}/emails/$emailId/clear-bounce";

        $this->crm('POST', $path, [], $this->ownerHeaders())->assertStatus(409);

        $this->insertLedger('ada@example.com', 'COMPLAINED', 'CAMPAIGN', null, now()->subDays(2));
        $this->crm('POST', $path, [], $this->ownerHeaders())->assertStatus(409);
        DB::table('crm_consent_events')->where('contact_id', $this->contactId)->where('action', 'COMPLAINED')->delete();

        $refusedId = $this->insertRecipient($this->campaignId, $this->contactId, 'ada@example.com', ['status' => 'FAILED', 'failure_reason' => 'PROVIDER_SUPPRESSED']);
        $this->insertLedger('ada@example.com', 'BOUNCED', 'CAMPAIGN', $refusedId, now()->subDays(10));

        $this->crm('POST', $path, [], $this->ownerHeaders())
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'email provider refused'));
        $availableAt = $this->crm('GET', "contacts/{$this->contactId}", [], $this->ownerHeaders())
            ->assertOk()
            ->json('data.suppression.reactivation_available_at');
        $this->assertSame(now()->subDays(10)->addDays(30)->toDateString(), Carbon::parse($availableAt)->toDateString());

        $this->travel(21)->days();
        $this->crm('GET', "contacts/{$this->contactId}", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.suppression.reason', 'BOUNCED')
            ->assertJsonPath('data.suppression.reactivation_available_at', null);
        $this->crm('POST', $path, [], $this->ownerHeaders())->assertOk();
        $this->assertSame(1, DB::table('crm_consent_events')->where('contact_id', $this->contactId)->where('action', 'BOUNCE_CLEARED')->count());
    }

    public function test_deleting_and_adding_a_contact_again_does_not_lift_the_wait_after_the_email_provider_refused_the_address(): void
    {
        $refusedId = $this->insertRecipient($this->campaignId, $this->contactId, 'ada@example.com', ['status' => 'FAILED', 'failure_reason' => 'PROVIDER_SUPPRESSED', 'updated_at' => now()->subDays(10)]);
        $this->insertLedger('ada@example.com', 'BOUNCED', 'CAMPAIGN', $refusedId, now()->subDays(10));

        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();
        $restoredId = (int) $this->crm('POST', 'contacts', ['email' => 'ada@example.com', 'restore_deleted' => true], $this->ownerHeaders())
            ->assertCreated()
            ->json('data.id');

        $this->assertSame('BOUNCED', $this->marketingStatus($restoredId));
        $path = "contacts/$restoredId/emails/{$this->emailId('ada@example.com')}/clear-bounce";
        $this->crm('POST', $path, [], $this->ownerHeaders())
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'email provider refused'));
        $availableAt = $this->crm('GET', "contacts/$restoredId", [], $this->ownerHeaders())
            ->assertOk()
            ->json('data.suppression.reactivation_available_at');
        $this->assertSame(now()->subDays(10)->addDays(30)->toDateString(), Carbon::parse($availableAt)->toDateString());

        $this->travel(21)->days();
        $this->crm('POST', $path, [], $this->ownerHeaders())->assertOk();
    }

    public function test_reactivating_an_email_of_another_contact_or_organizer_is_not_found(): void
    {
        $this->crm('POST', "contacts/{$this->contactId}/emails/{$this->otherEmailId}/clear-bounce", [], $this->ownerHeaders())->assertNotFound();
        $this->crm('POST', "contacts/{$this->secondContactId}/emails/{$this->aliasEmailId}/clear-bounce", [], $this->ownerHeaders())->assertNotFound();
    }

    public function test_the_suppressed_list_explains_each_reason_and_where_it_came_from(): void
    {
        $recipientId = $this->insertRecipient($this->campaignId, $this->contactId, 'ada@example.com', ['status' => 'FAILED', 'failure_reason' => 'PROVIDER_SUPPRESSED']);
        $this->insertLedger('ada@example.com', 'BOUNCED', 'CAMPAIGN', $recipientId, now()->subDay());
        $this->crm('POST', "contacts/{$this->secondContactId}/unsubscribe", [], $this->ownerHeaders())->assertOk();
        $this->insertContact('plain@example.com');

        $response = $this->crm('GET', 'contacts?with_suppression=1&sort_by=email_marketing_status_changed_at&sort_direction=desc&definition='.urlencode(json_encode(self::SUPPRESSED_DEFINITION)), [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $rows = collect($response->json('data'))->keyBy('email');
        $this->assertSame(['reason' => 'BOUNCED', 'source_type' => 'CAMPAIGN', 'campaign_name' => 'Spring news', 'provider_rejected' => true], array_intersect_key($rows['ada@example.com']['suppression'], array_flip(['reason', 'source_type', 'campaign_name', 'provider_rejected'])));
        $this->assertSame(['UNSUBSCRIBED', 'MANUAL', trim($this->owner->first_name.' '.$this->owner->last_name)], [
            $rows['second@example.com']['suppression']['reason'],
            $rows['second@example.com']['suppression']['source_type'],
            $rows['second@example.com']['suppression']['user_name'],
        ]);

        $this->crm('GET', 'contacts', [], $this->ownerHeaders())->assertOk()->assertJsonMissingPath('data.0.suppression');
    }

    public function test_the_summary_counts_each_reason_and_deleted_contacts_that_keep_a_suppression(): void
    {
        $this->crm('POST', "contacts/{$this->secondContactId}/unsubscribe", [], $this->ownerHeaders())->assertOk();
        $this->insertLedger('ada@example.com', 'COMPLAINED', 'CAMPAIGN', null, now()->subDay());
        $gone = $this->insertSubscribedContact('gone@example.com');
        $this->insertLedger('gone@example.com', 'BOUNCED', 'CAMPAIGN', null, now()->subHour(), $gone);
        $this->crm('DELETE', "contacts/$gone", [], $this->ownerHeaders())->assertNoContent();

        $this->crm('GET', 'suppressions/summary', [], $this->ownerHeaders())
            ->assertOk()
            ->assertExactJson(['data' => ['unsubscribed' => 1, 'bounced' => 0, 'complained' => 1, 'deleted' => 1]]);
    }

    public function test_looking_up_addresses_covers_contacts_deleted_contacts_unknown_and_invalid_addresses(): void
    {
        $this->insertLedger('alias@example.com', 'COMPLAINED', 'CAMPAIGN', null, now()->subDay());
        $gone = $this->insertSubscribedContact('gone@example.com');
        $this->crm('POST', "contacts/$gone/unsubscribe", [], $this->ownerHeaders())->assertOk();
        $this->crm('DELETE', "contacts/$gone", [], $this->ownerHeaders())->assertNoContent();
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        $response = $this->crm('POST', 'suppressions/lookup', ['emails' => [' Alias@Example.com ', 'gone@example.com', 'new@example.com', 'not-an-email', 'alias@example.com', 'other@example.com']], $this->memberHeaders($viewer))
            ->assertOk();

        $this->assertSame(
            [
                ['alias@example.com', 'contact'],
                ['gone@example.com', 'deleted'],
                ['new@example.com', 'not_found'],
                ['not-an-email', 'invalid'],
                ['other@example.com', 'not_found'],
            ],
            array_map(static fn (array $row) => [$row['email'], $row['result']], $response->json('data')),
        );
        $response
            ->assertJsonPath('data.0.contact.id', $this->contactId)
            ->assertJsonPath('data.0.contact.email_marketing_status', 'COMPLAINED')
            ->assertJsonPath('data.0.suppression.reason', 'COMPLAINED')
            ->assertJsonPath('data.1.deleted_suppression', 'UNSUBSCRIBED');

        $this->crm('POST', 'suppressions/lookup', ['emails' => array_map(static fn (int $index) => "person$index@example.com", range(1, 51))], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('emails');
    }

    public function test_a_suppression_import_suppresses_existing_contacts_creates_unknown_ones_and_keeps_deleted_ones_deleted(): void
    {
        $gone = $this->insertSubscribedContact('gone@example.com');
        $this->crm('DELETE', "contacts/$gone", [], $this->ownerHeaders())->assertNoContent();
        $this->crm('POST', "contacts/{$this->secondContactId}/unsubscribe", [], $this->ownerHeaders())->assertOk();
        $hash = app(DeletedContactHasher::class)->hash($this->organizerId, 'gone@example.com');
        $this->assertNull(DB::table('crm_deleted_contacts')->where('email_hash', $hash)->value('suppression'));

        $csv = implode("\n", [
            'Email,Reason',
            'ada@example.com,unsubscribed',
            'new@example.com,cleaned',
            'gone@example.com,spam',
            'second@example.com,',
            'second@example.com,bounced',
            'nope,bounced',
            '',
        ]);

        $this->post("/organizers/{$this->organizerId}/crm/imports", [
            'file' => UploadedFile::fake()->createWithContent('mailchimp.csv', $csv),
            'kind' => 'SUPPRESSIONS',
        ], ['Accept' => 'application/json', ...$this->ownerHeaders()])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'SUPPRESSIONS')
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.updated_count', 1)
            ->assertJsonPath('data.skipped_reasons', ['invalid_email' => 1, 'duplicate_in_file' => 1, 'previously_deleted' => 1, 'already_suppressed' => 1, 'bounced_secondary_address' => 0]);

        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus($this->contactId));
        $this->assertSame('UNSUBSCRIBED', $this->marketingStatus($this->secondContactId));
        $new = $this->contact('new@example.com');
        $this->assertSame(['IMPORT', 'BOUNCED'], [$new->source, $new->email_marketing_status]);
        $this->assertSame('COMPLAINED', DB::table('crm_deleted_contacts')->where('email_hash', $hash)->value('suppression'));
        $this->assertNull($this->contact('gone@example.com'));
        $this->assertSame(0, DB::table('crm_consent_events')->where('action', 'OPT_IN')->where('source_type', 'IMPORT')->count());

        $this->crm('GET', "contacts/{$this->contactId}/consent", [], $this->ownerHeaders())
            ->assertJsonPath('data.0.source', 'IMPORT')
            ->assertJsonPath('data.0.import_file_name', 'mailchimp.csv');
    }

    public function test_an_imported_unsubscribe_gives_way_to_a_later_opt_in_but_an_imported_bounce_does_not(): void
    {
        $this->importSuppressions("Email,Reason\nada@example.com,unsubscribed\nsecond@example.com,bounced\n")->assertCreated();
        $this->assertSame(['UNSUBSCRIBED', 'BOUNCED'], [$this->marketingStatus($this->contactId), $this->marketingStatus($this->secondContactId)]);

        $this->insertLedger('ada@example.com', 'OPT_IN', 'ORDER', null, now()->addMinute());
        $this->insertLedger('second@example.com', 'OPT_IN', 'ORDER', null, now()->addMinute(), $this->secondContactId);

        $this->assertSame(['SUBSCRIBED', 'BOUNCED'], [$this->marketingStatus($this->contactId), $this->marketingStatus($this->secondContactId)]);
    }

    public function test_the_import_preview_counts_what_would_change_without_writing_anything(): void
    {
        $gone = $this->insertSubscribedContact('gone@example.com');
        $this->crm('DELETE', "contacts/$gone", [], $this->ownerHeaders())->assertNoContent();
        $before = $this->crmState();

        $this->post("/organizers/{$this->organizerId}/crm/imports/suppressions/preview", [
            'file' => UploadedFile::fake()->createWithContent('list.csv', "Email,Reason\nada@example.com,\nALIAS@example.com,spam\nnew@example.com,bounce\ngone@example.com,\nnew@example.com,\nbad,\n"),
        ], ['Accept' => 'application/json', ...$this->ownerHeaders()])
            ->assertOk()
            ->assertExactJson(['data' => [
                'total_rows' => 6,
                'valid_rows' => 4,
                'invalid_rows' => 1,
                'duplicate_rows' => 1,
                'new_contacts' => 1,
                'existing_contacts' => 1,
                'subscribed_contacts' => 1,
                'deleted_contacts' => 1,
                'reasons' => ['UNSUBSCRIBED' => 3, 'BOUNCED' => 0, 'COMPLAINED' => 1],
                'sample' => [
                    ['email' => 'ada@example.com', 'reason' => 'UNSUBSCRIBED'],
                    ['email' => 'alias@example.com', 'reason' => 'COMPLAINED'],
                    ['email' => 'new@example.com', 'reason' => 'UNSUBSCRIBED'],
                    ['email' => 'gone@example.com', 'reason' => 'UNSUBSCRIBED'],
                ],
            ]]);

        $this->assertSame($before, $this->crmState());
    }

    public function test_a_suppression_import_refuses_options_that_only_apply_to_contact_imports(): void
    {
        foreach ([['consent_attested' => '1'], ['consent_attested' => '0'], ['tag_name' => 'Imported']] as $fields) {
            $this->post("/organizers/{$this->organizerId}/crm/imports", [
                'file' => UploadedFile::fake()->createWithContent('suppressions.csv', "Email\nada@example.com\n"),
                'kind' => 'SUPPRESSIONS',
                ...$fields,
            ], ['Accept' => 'application/json', ...$this->ownerHeaders()])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(array_keys($fields));
        }

        $this->assertSame(0, DB::table('crm_imports')->where('organizer_id', $this->organizerId)->count());
        $this->assertSame(0, DB::table('crm_tags')->where('organizer_id', $this->organizerId)->where('name', 'Imported')->count());
    }

    public function test_an_import_with_no_valid_address_is_refused_before_anything_is_written(): void
    {
        $this->importSuppressions("Email\nnot-an-email\n")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, DB::table('crm_imports')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_a_bounce_imported_for_both_addresses_of_one_contact_suppresses_it_whichever_address_is_the_primary(): void
    {
        DB::table('crm_contacts')->where('id', $this->contactId)->update(['email' => 'alias@example.com']);

        $this->importSuppressions("Email,Reason\nada@example.com,bounced\nalias@example.com,bounced\n")
            ->assertCreated()
            ->assertJsonPath('data.updated_count', 1);

        $this->assertSame('BOUNCED', $this->marketingStatus($this->contactId));

        $this->crm('POST', "contacts/{$this->contactId}/emails/{$this->emailId('ada@example.com')}/primary", [], $this->ownerHeaders())->assertOk();

        $this->assertSame('BOUNCED', $this->marketingStatus($this->contactId));
    }

    public function test_a_bounce_imported_only_for_a_secondary_address_is_recorded_but_not_reported_as_suppressing_the_contact(): void
    {
        $csv = "Email,Reason\nalias@example.com,bounced\n";

        $this->post("/organizers/{$this->organizerId}/crm/imports/suppressions/preview", [
            'file' => UploadedFile::fake()->createWithContent('list.csv', $csv),
        ], ['Accept' => 'application/json', ...$this->ownerHeaders()])
            ->assertOk()
            ->assertJsonPath('data.existing_contacts', 1)
            ->assertJsonPath('data.subscribed_contacts', 0);

        $this->importSuppressions($csv)
            ->assertCreated()
            ->assertJsonPath('data.updated_count', 0)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.skipped_reasons.bounced_secondary_address', 1);

        $this->assertSame('SUBSCRIBED', $this->marketingStatus($this->contactId));
        $this->assertSame(
            ['alias@example.com'],
            DB::table('crm_consent_events')->where('contact_id', $this->contactId)->where('action', 'BOUNCED')->pluck('email')->all(),
        );
    }

    public function test_an_unsubscribe_or_complaint_imported_for_a_secondary_address_suppresses_the_contact_and_says_so(): void
    {
        $csv = "Email,Reason\nalias@example.com,complained\n";

        $this->post("/organizers/{$this->organizerId}/crm/imports/suppressions/preview", [
            'file' => UploadedFile::fake()->createWithContent('list.csv', $csv),
        ], ['Accept' => 'application/json', ...$this->ownerHeaders()])
            ->assertOk()
            ->assertJsonPath('data.subscribed_contacts', 1);

        $this->importSuppressions($csv)
            ->assertCreated()
            ->assertJsonPath('data.updated_count', 1)
            ->assertJsonPath('data.skipped_reasons.bounced_secondary_address', 0);

        $this->assertSame('COMPLAINED', $this->marketingStatus($this->contactId));
    }

    public function test_the_import_summary_counts_a_contact_listed_under_two_addresses_once(): void
    {
        $this->insertConsent($this->contactId, 'ada@example.com', 'OPT_OUT', now()->subDay());
        $this->insertConsent($this->contactId, 'alias@example.com', 'OPT_OUT', now()->subDay());

        $this->importSuppressions("Email\nada@example.com\nalias@example.com\n")
            ->assertCreated()
            ->assertJsonPath('data.updated_count', 0)
            ->assertJsonPath('data.skipped_reasons.already_suppressed', 1);
    }

    public function test_a_bounce_for_a_secondary_address_then_the_primary_in_a_later_chunk_of_one_import_suppresses_the_contact(): void
    {
        $this->assertSame('SUBSCRIBED', $this->marketingStatus($this->contactId));
        $importId = DB::table('crm_imports')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'user_id' => $this->owner->id,
            'file_name' => 'bounces.csv',
            'kind' => 'SUPPRESSIONS',
            'status' => 'PROCESSING',
            'total_rows' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $salt = app(DeletedContactHasher::class)->salt($this->organizerId);
        $chunk = fn (string $email) => app(CrmContactRepositoryInterface::class)->importSuppressions(
            accountId: $this->accountId,
            organizerId: $this->organizerId,
            importId: $importId,
            userId: $this->owner->id,
            rows: [['email' => $email, 'first_name' => null, 'last_name' => null, 'reason' => CrmEmailMarketingStatus::BOUNCED]],
            deletedContactSalt: $salt,
        );

        $chunk('alias@example.com');
        app(CrmProjectionRepositoryInterface::class)->refreshContacts($this->organizerId, [$this->contactId]);
        $this->assertSame('SUBSCRIBED', $this->marketingStatus($this->contactId));

        $chunk('ada@example.com');
        app(CrmProjectionRepositoryInterface::class)->refreshContacts($this->organizerId, [$this->contactId]);

        $this->assertSame('BOUNCED', $this->marketingStatus($this->contactId));
        $this->assertSame(
            ['ada@example.com', 'alias@example.com'],
            DB::table('crm_consent_events')->where('contact_id', $this->contactId)->where('action', 'BOUNCED')->orderBy('email')->pluck('email')->all(),
        );
    }

    public function test_a_deleted_contact_that_keeps_a_suppression_is_counted_but_never_listed(): void
    {
        $this->crm('POST', "contacts/{$this->secondContactId}/unsubscribe", [], $this->ownerHeaders())->assertOk();
        $gone = $this->insertSubscribedContact('gone@example.com');
        $this->insertLedger('gone@example.com', 'BOUNCED', 'CAMPAIGN', null, now()->subHour(), $gone);
        $this->crm('DELETE', "contacts/$gone", [], $this->ownerHeaders())->assertNoContent();

        $listed = $this->crm('GET', 'contacts?with_suppression=1&definition='.urlencode(json_encode(self::SUPPRESSED_DEFINITION)), [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertSame(['second@example.com'], array_column($listed->json('data'), 'email'));
        $this->assertNotContains('gone@example.com', array_column($this->crm('GET', 'contacts?per_page=100', [], $this->ownerHeaders())->json('data'), 'email'));
        $this->crm('GET', 'suppressions/summary', [], $this->ownerHeaders())->assertJsonPath('data.deleted', 1);
    }

    public function test_an_imported_reason_never_replaces_a_complaint_or_the_same_suppression_of_a_contact_or_a_deleted_contact(): void
    {
        $this->insertLedger('ada@example.com', 'COMPLAINED', 'CAMPAIGN', null, now()->subDay());
        $this->crm('POST', "contacts/{$this->secondContactId}/unsubscribe", [], $this->ownerHeaders())->assertOk();
        $gone = $this->insertSubscribedContact('gone@example.com');
        $this->insertLedger('gone@example.com', 'COMPLAINED', 'CAMPAIGN', null, now()->subHour(), $gone);
        $this->crm('DELETE', "contacts/$gone", [], $this->ownerHeaders())->assertNoContent();
        $hash = app(DeletedContactHasher::class)->hash($this->organizerId, 'gone@example.com');
        $this->assertSame('COMPLAINED', DB::table('crm_deleted_contacts')->where('email_hash', $hash)->value('suppression'));
        $eventsBefore = DB::table('crm_consent_events')->where('organizer_id', $this->organizerId)->count();

        $this->importSuppressions("Email,Reason\nada@example.com,bounced\nsecond@example.com,unsubscribed\ngone@example.com,unsubscribed\n")
            ->assertCreated()
            ->assertJsonPath('data.created_count', 0)
            ->assertJsonPath('data.updated_count', 0)
            ->assertJsonPath('data.skipped_reasons.already_suppressed', 2)
            ->assertJsonPath('data.skipped_reasons.previously_deleted', 1);

        $this->assertSame(['COMPLAINED', 'UNSUBSCRIBED'], [$this->marketingStatus($this->contactId), $this->marketingStatus($this->secondContactId)]);
        $this->assertSame('COMPLAINED', DB::table('crm_deleted_contacts')->where('email_hash', $hash)->value('suppression'));
        $this->assertSame($eventsBefore, DB::table('crm_consent_events')->where('organizer_id', $this->organizerId)->count());
        $this->assertNull($this->contact('gone@example.com'));
    }

    public function test_a_stronger_imported_reason_replaces_a_weaker_suppression(): void
    {
        $this->crm('POST', "contacts/{$this->contactId}/unsubscribe", [], $this->ownerHeaders())->assertOk();
        $this->insertLedger('second@example.com', 'BOUNCED', 'CAMPAIGN', null, now()->subDay());

        $this->importSuppressions("Email,Reason\nada@example.com,bounced\nsecond@example.com,spam\n")
            ->assertCreated()
            ->assertJsonPath('data.updated_count', 2);

        $this->assertSame(['BOUNCED', 'COMPLAINED'], [$this->marketingStatus($this->contactId), $this->marketingStatus($this->secondContactId)]);
    }

    public function test_an_imported_unsubscribe_is_recorded_for_a_bounced_contact_so_clearing_the_bounce_leaves_them_unsubscribed(): void
    {
        $this->insertLedger('ada@example.com', 'BOUNCED', 'CAMPAIGN', null, now()->subDays(40));

        $this->importSuppressions("Email,Reason\nada@example.com,unsubscribed\n")
            ->assertCreated()
            ->assertJsonPath('data.updated_count', 1)
            ->assertJsonPath('data.skipped_reasons.already_suppressed', 0);

        $this->assertSame('BOUNCED', $this->marketingStatus($this->contactId));
        $this->crm('POST', "contacts/{$this->contactId}/emails/{$this->emailId('ada@example.com')}/clear-bounce", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.email_marketing_status', 'UNSUBSCRIBED');
    }

    public static function importedReasonsForDeletedContacts(): array
    {
        return [
            'an unsubscribe outranks the bounce they were deleted with' => ['BOUNCED', 'unsubscribed', 'UNSUBSCRIBED'],
            'a bounce never replaces the opt-out they were deleted with' => ['OPT_OUT', 'bounced', 'UNSUBSCRIBED'],
            'a complaint outranks the opt-out they were deleted with' => ['OPT_OUT', 'spam', 'COMPLAINED'],
            'an unsubscribe never replaces a complaint' => ['COMPLAINED', 'unsubscribed', 'COMPLAINED'],
        ];
    }

    #[DataProvider('importedReasonsForDeletedContacts')]
    public function test_a_suppression_import_ranks_a_deleted_contacts_reasons_complaint_then_opt_out_then_bounce(string $heldWhenDeleted, string $imported, string $suppression): void
    {
        $gone = $this->insertSubscribedContact('gone@example.com');
        $this->insertLedger('gone@example.com', $heldWhenDeleted, 'CAMPAIGN', null, now()->subHour(), $gone);
        $this->crm('DELETE', "contacts/$gone", [], $this->ownerHeaders())->assertNoContent();

        $this->importSuppressions("Email,Reason\ngone@example.com,$imported\n")->assertCreated();

        $this->assertSame(
            $suppression,
            DB::table('crm_deleted_contacts')->where('email_hash', app(DeletedContactHasher::class)->hash($this->organizerId, 'gone@example.com'))->value('suppression'),
        );
        $this->assertNull($this->contact('gone@example.com'));
    }

    private function importSuppressions(string $csv): TestResponse
    {
        return $this->post("/organizers/{$this->organizerId}/crm/imports", [
            'file' => UploadedFile::fake()->createWithContent('suppressions.csv', $csv),
            'kind' => 'SUPPRESSIONS',
        ], ['Accept' => 'application/json', ...$this->ownerHeaders()]);
    }

    private function insertLedger(string $email, string $action, string $source, ?int $sourceId, $occurredAt, ?int $contactId = null): void
    {
        $contactId ??= $email === 'second@example.com' ? $this->secondContactId : $this->contactId;

        DB::table('crm_consent_events')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $contactId,
            'email' => $email,
            'action' => $action,
            'source_type' => $source,
            'source_id' => $sourceId,
            'occurred_at' => $occurredAt,
        ]);

        app(CrmProjectionRepositoryInterface::class)->refreshContacts($this->organizerId, [$contactId]);
    }

    private function marketingStatus(int $contactId): string
    {
        return DB::table('crm_contacts')->where('id', $contactId)->value('email_marketing_status');
    }

    private function emailId(string $email): int
    {
        return (int) DB::table('crm_contact_emails')->where('organizer_id', $this->organizerId)->where('email', $email)->value('id');
    }
}
