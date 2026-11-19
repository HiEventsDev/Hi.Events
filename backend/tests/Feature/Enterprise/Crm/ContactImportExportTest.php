<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Jobs\ProcessContactImportChunkJob;
use HiEvents\Enterprise\Crm\Jobs\PruneCrmBulkOperationsJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO\ImportContactsDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\ImportContactsHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag\UpsertTagHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Import\ContactImportParser;
use HiEvents\Enterprise\Crm\Services\Domain\Import\ContactImportRunner;
use HiEvents\Services\Infrastructure\Export\SpreadsheetFormulaEscaper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ContactImportExportTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
    }

    public function test_an_import_creates_new_contacts_and_reports_what_happened_to_every_row(): void
    {
        $this->crm('DELETE', "contacts/{$this->secondContactId}", [], $this->ownerHeaders())->assertNoContent();
        $csv = implode("\n", [
            "\xEF\xBB\xBFE-mail;First Name;Last Name",
            ' Grace@Example.com ;Grace;Hopper',
            'grace@example.com;Duplicate;Row',
            'not-an-email;Bad;Row',
            ';Missing;Email',
            'ada@example.com;Changed;Name',
            'ALIAS@example.com;Alias;Row',
            'second@example.com;Deleted;Contact',
            'linus@example.com;https://spam.example;Torvalds',
            '',
        ]);

        $response = $this->import($csv)
            ->assertCreated()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.file_name', 'contacts.csv')
            ->assertJsonPath('data.consent_attested', false)
            ->assertJsonPath('data.total_rows', 8)
            ->assertJsonPath('data.created_count', 2)
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.skipped_count', 4)
            ->assertJsonPath('data.skipped_reasons', ['invalid_email' => 2, 'duplicate_in_file' => 1, 'previously_deleted' => 1, 'already_suppressed' => 0, 'bounced_secondary_address' => 0]);

        $this->assertSame(['ada@example.com', 'grace@example.com', 'linus@example.com'], $this->contactEmails());
        $grace = $this->contact('grace@example.com');
        $this->assertSame(['Grace', 'Hopper', 'IMPORT', 'NOT_SUBSCRIBED', $this->accountId], [$grace->first_name, $grace->last_name, $grace->source, $grace->email_marketing_status, (int) $grace->account_id]);
        $this->assertSame(1, DB::table('crm_contact_emails')->where('contact_id', $grace->id)->where('email', 'grace@example.com')->count());
        $linus = $this->contact('linus@example.com');
        $this->assertSame([null, 'Torvalds'], [$linus->first_name, $linus->last_name]);
        $ada = $this->contact('ada@example.com');
        $this->assertSame(['Ada', 'Lovelace', 'ORDER', 1], [$ada->first_name, $ada->last_name, $ada->source, (int) $ada->orders_count]);
        $this->assertSame(0, DB::table('crm_consent_events')->where('source_type', 'IMPORT')->count());
        $this->assertSame(0, DB::table('crm_contact_tags')->count());

        $import = DB::table('crm_imports')->where('id', $response->json('data.id'))->sole();
        $this->assertSame([$this->accountId, $this->organizerId, $this->owner->id], [(int) $import->account_id, (int) $import->organizer_id, (int) $import->user_id]);
    }

    public function test_recent_imports_are_listed_newest_first_for_the_organizer_only(): void
    {
        $first = $this->import("Email\nfirst@example.com\n", fileName: 'first.csv')->assertCreated()->json('data.id');
        $second = $this->import("Email\nsecond-import@example.com\nbad\n", fileName: 'second.csv')->assertCreated()->json('data.id');
        DB::table('crm_imports')->insert([
            'account_id' => $this->accountId,
            'organizer_id' => $this->otherOrganizerId,
            'file_name' => 'other.csv',
            'status' => 'COMPLETED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->crm('GET', 'imports', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second)
            ->assertJsonPath('data.0.file_name', 'second.csv')
            ->assertJsonPath('data.0.skipped_count', 1)
            ->assertJsonPath('data.0.user_name', trim($this->owner->first_name.' '.$this->owner->last_name))
            ->assertJsonPath('data.1.id', $first);
    }

    public function test_only_the_twenty_most_recent_imports_are_listed(): void
    {
        DB::table('crm_imports')->insert(array_map(fn (int $index) => [
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'file_name' => "file-$index.csv",
            'status' => 'COMPLETED',
            'created_at' => now()->subMinutes(30 - $index),
            'updated_at' => now(),
        ], range(1, 25)));

        $response = $this->crm('GET', 'imports', [], $this->ownerHeaders())->assertOk()->assertJsonCount(20, 'data');

        $this->assertSame('file-25.csv', $response->json('data.0.file_name'));
    }

    public function test_importing_the_same_file_twice_duplicates_nothing(): void
    {
        $csv = "Email,Name\ngrace@example.com,Grace Brewster Hopper\n";

        $this->import($csv)->assertCreated()->assertJsonPath('data.created_count', 1);
        $this->import($csv)->assertCreated()->assertJsonPath('data.created_count', 0)->assertJsonPath('data.updated_count', 1)->assertJsonPath('data.skipped_count', 0);

        $this->assertSame(1, DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->where('email', 'grace@example.com')->count());
        $grace = $this->contact('grace@example.com');
        $this->assertSame(['Grace', 'Brewster Hopper'], [$grace->first_name, $grace->last_name]);
        $this->assertSame(0, DB::table('crm_contacts')->where('organizer_id', $this->otherOrganizerId)->where('email', 'grace@example.com')->count());
    }

    public function test_an_email_known_to_another_organizer_is_still_created_for_this_one(): void
    {
        $this->import("email\nother@example.com\n")->assertCreated()->assertJsonPath('data.created_count', 1);

        $this->assertNotNull($this->contact('other@example.com'));
        $this->assertSame($this->otherContactId, (int) $this->contact('other@example.com', $this->otherOrganizerId)->id);
    }

    public function test_attesting_consent_subscribes_only_contacts_created_by_the_import(): void
    {
        $statuses = ['unsubscribed' => 'OPT_OUT', 'bounced' => 'BOUNCED', 'complained' => 'COMPLAINED'];
        foreach ($statuses as $name => $action) {
            $contactId = $this->insertContact("$name@example.com", ['email_marketing_status' => strtoupper($name)]);
            $this->insertConsent($contactId, "$name@example.com", $action, now()->subDay());
        }
        $csv = "Email\nnew@example.com\nsecond@example.com\nada@example.com\nunsubscribed@example.com\nbounced@example.com\ncomplained@example.com\n";

        $importId = $this->import($csv, ['consent_attested' => true])
            ->assertCreated()
            ->assertJsonPath('data.consent_attested', true)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.updated_count', 5)
            ->json('data.id');

        $this->assertSame(
            [
                'ada@example.com' => 'SUBSCRIBED',
                'bounced@example.com' => 'BOUNCED',
                'complained@example.com' => 'COMPLAINED',
                'new@example.com' => 'SUBSCRIBED',
                'second@example.com' => 'NOT_SUBSCRIBED',
                'unsubscribed@example.com' => 'UNSUBSCRIBED',
            ],
            DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->orderBy('email')->pluck('email_marketing_status', 'email')->all(),
        );
        $event = DB::table('crm_consent_events')->where('source_type', 'IMPORT')->sole();
        $this->assertSame(
            ['new@example.com', 'OPT_IN', $importId, $this->owner->id],
            [$event->email, $event->action, (int) $event->source_id, (int) $event->user_id],
        );
    }

    public function test_an_attested_import_leaves_an_existing_buyer_who_never_opted_in_not_subscribed(): void
    {
        $orderId = $this->insertOrder(['email' => 'buyer@example.com']);
        $this->insertAttendee($orderId);
        $this->projectOrders($orderId);
        $this->assertSame('NOT_SUBSCRIBED', $this->contact('buyer@example.com')->email_marketing_status);

        $this->import("Email\nbuyer@example.com\n", ['consent_attested' => true])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 0)
            ->assertJsonPath('data.updated_count', 1);
        $this->projectOrders($orderId);

        $this->assertSame('NOT_SUBSCRIBED', $this->contact('buyer@example.com')->email_marketing_status);
        $this->assertSame(0, DB::table('crm_consent_events')->where('email', 'buyer@example.com')->count());
        $this->assertSame(0, DB::table('crm_contact_activities')->where('email', 'buyer@example.com')->where('type', 'IMPORTED')->count());
    }

    public function test_an_import_records_an_imported_activity_only_for_the_contacts_it_created(): void
    {
        $importId = $this->import("Email\nnew@example.com\nfresh@example.com\nsecond@example.com\n")->assertCreated()->json('data.id');
        $this->import("Email\nnew@example.com\n")->assertCreated();

        $activities = DB::table('crm_contact_activities')->where('organizer_id', $this->organizerId)->where('type', 'IMPORTED')->orderBy('email')->get();

        $this->assertSame(['fresh@example.com', 'new@example.com'], $activities->pluck('email')->all());
        foreach ($activities as $activity) {
            $this->assertSame(['IMPORT', $importId, $this->contactId($activity->email)], [$activity->source_type, (int) $activity->source_id, (int) $activity->contact_id]);
        }
    }

    public function test_without_attestation_nobody_is_subscribed(): void
    {
        foreach ([[], ['consent_attested' => false], ['consent_attested' => '0']] as $fields) {
            $this->import("Email\nnew@example.com\nsecond@example.com\n", $fields)->assertCreated()->assertJsonPath('data.consent_attested', false);
        }

        $this->assertSame('NOT_SUBSCRIBED', $this->contact('new@example.com')->email_marketing_status);
        $this->assertSame('NOT_SUBSCRIBED', $this->contact('second@example.com')->email_marketing_status);
        $this->assertSame(0, DB::table('crm_consent_events')->where('source_type', 'IMPORT')->count());
    }

    public function test_a_deleted_contact_is_not_recreated_or_subscribed_by_an_import(): void
    {
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $this->ownerHeaders())->assertNoContent();

        $this->import("Email\nada@example.com\nalias@example.com\nnew@example.com\n", ['consent_attested' => true, 'tag_name' => 'Imported'])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.skipped_count', 2);

        $this->assertNull($this->contact('ada@example.com'));
        $this->assertNull($this->contact('alias@example.com'));
        $this->assertSame(0, DB::table('crm_contact_emails')->whereIn('email', ['ada@example.com', 'alias@example.com'])->count());
        $this->assertSame(0, DB::table('crm_consent_events')->whereIn('email', ['ada@example.com', 'alias@example.com'])->count());
    }

    public function test_the_import_tag_is_created_once_reused_and_applied_to_new_and_existing_contacts(): void
    {
        $this->import("Email\nnew@example.com\nada@example.com\n", ['tag_name' => '  Newsletter '])->assertCreated();
        $this->import("Email\nlater@example.com\nada@example.com\n", ['tag_name' => 'newsletter'])->assertCreated();
        $this->import("Email\nvip@example.com\n", ['tag_name' => 'vip'])->assertCreated();

        $tag = DB::table('crm_tags')->where('organizer_id', $this->organizerId)->whereRaw('lower(name) = ?', ['newsletter'])->sole();
        $this->assertSame('Newsletter', $tag->name);
        $this->assertSame(
            ['ada@example.com', 'later@example.com', 'new@example.com'],
            DB::table('crm_contact_tags')->join('crm_contacts', 'crm_contacts.id', '=', 'crm_contact_tags.contact_id')->where('tag_id', $tag->id)->orderBy('email')->pluck('email')->all(),
        );
        $this->assertSame([$this->tagId], DB::table('crm_contact_tags')->where('contact_id', $this->contactId('vip@example.com'))->pluck('tag_id')->all());
        $this->assertSame(2, DB::table('crm_tags')->where('organizer_id', $this->organizerId)->count());
        $this->assertSame(1, DB::table('crm_tags')->where('organizer_id', $this->otherOrganizerId)->count());
    }

    public static function unusableFiles(): array
    {
        return [
            'no email column' => ["Name,Phone\nGrace,123\n", 'contacts.csv'],
            'no header row' => ["grace@example.com,Grace\n", 'contacts.csv'],
            'no valid emails' => ["Email\nnot-an-email\n\n", 'contacts.csv'],
            'empty file' => ['', 'contacts.csv'],
            'more rows than the limit' => ['too_many', 'contacts.csv'],
            'not a csv' => ["Email\ngrace@example.com\n", 'contacts.exe'],
        ];
    }

    #[DataProvider('unusableFiles')]
    public function test_an_unusable_file_is_rejected_with_a_file_error_and_writes_nothing(string $csv, string $fileName): void
    {
        if ($csv === 'too_many') {
            $csv = "Email\n".implode("\n", array_map(static fn (int $index) => "person$index@example.com", range(1, ContactImportParser::MAX_ROWS + 1)));
        }
        $before = $this->crmState();

        $this->import($csv, ['consent_attested' => true, 'tag_name' => 'Imported'], $fileName)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertSame($before, $this->crmState());
    }

    public function test_an_import_that_breaks_on_a_later_chunk_is_marked_failed_and_keeps_the_chunks_already_imported(): void
    {
        $csv = "Email\n".implode("\n", array_map(static fn (int $index) => "person$index@example.com", range(1, 501)));
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);
        $realRepository = app(CrmContactRepositoryInterface::class);
        $contactRepository = Mockery::mock(CrmContactRepositoryInterface::class);
        $contactRepository->shouldReceive('findOrCreateDeletedContactSalt')->andReturnUsing(static fn (int $organizerId) => $realRepository->findOrCreateDeletedContactSalt($organizerId));
        $contactRepository->shouldReceive('importContacts')->once()->ordered()->andReturnUsing(static fn (...$arguments) => $realRepository->importContacts(...$arguments));
        $contactRepository->shouldReceive('importContacts')->once()->ordered()->andThrow(new RuntimeException('The database went away.'));
        $this->app->instance(CrmContactRepositoryInterface::class, $contactRepository);

        try {
            app(ImportContactsHandler::class)->handle(new ImportContactsDTO(
                account_id: $this->accountId,
                organizer_id: $this->organizerId,
                user_id: $this->owner->id,
                file_path: $file->getRealPath(),
                file_name: 'contacts.csv',
                consent_attested: false,
                tag_name: null,
            ));
            $this->fail('The import swallowed the failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The database went away.', $exception->getMessage());
        }

        $import = DB::table('crm_imports')->where('organizer_id', $this->organizerId)->sole();
        $this->assertSame(
            ['FAILED', 501, ContactImportRunner::CHUNK_SIZE, 0, 0],
            [$import->status, (int) $import->total_rows, (int) $import->created_count, (int) $import->updated_count, (int) $import->skipped_count],
        );
        $this->assertSame(ContactImportRunner::CHUNK_SIZE, DB::table('crm_contacts')->where('organizer_id', $this->organizerId)->where('email', 'like', 'person%')->count());
        $this->assertSame(0, DB::table('crm_import_rows')->where('import_id', $import->id)->count());

        $this->app->instance(CrmContactRepositoryInterface::class, $realRepository);
        $this->import($csv)
            ->assertCreated()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.updated_count', ContactImportRunner::CHUNK_SIZE);
    }

    public function test_a_file_too_big_for_one_chunk_is_imported_by_queued_jobs_one_chunk_at_a_time(): void
    {
        Bus::fake([ProcessContactImportChunkJob::class]);
        $tagId = $this->insertTag('Imported');
        $rows = 2 * ContactImportRunner::CHUNK_SIZE + 1;
        $csv = "Email\n".implode("\n", array_map(static fn (int $index) => "person$index@example.com", range(1, $rows)))."\nnot-an-email\n";

        $importId = $this->import($csv, ['tag_name' => 'Imported'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'PROCESSING')
            ->assertJsonPath('data.total_rows', $rows + 1)
            ->assertJsonPath('data.created_count', 0)
            ->assertJsonPath('data.skipped_count', 1)
            ->json('data.id');

        $this->assertSame(0, DB::table('crm_contacts')->where('email', 'like', 'person%')->count());
        Bus::assertDispatchedTimes(ProcessContactImportChunkJob::class, 1);

        foreach ([ContactImportRunner::CHUNK_SIZE, 2 * ContactImportRunner::CHUNK_SIZE, $rows] as $chunk => $imported) {
            (new ProcessContactImportChunkJob($importId, $tagId))->handle(app(ContactImportRunner::class));

            $import = DB::table('crm_imports')->where('id', $importId)->sole();
            $this->assertSame([$imported === $rows ? 'COMPLETED' : 'PROCESSING', $imported], [$import->status, (int) $import->created_count]);
            $this->assertSame($imported, DB::table('crm_contact_tags')->where('tag_id', $tagId)->count());
            Bus::assertDispatchedTimes(ProcessContactImportChunkJob::class, min($chunk + 2, 3));
        }

        $this->crm('GET', 'imports', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.status', 'COMPLETED')
            ->assertJsonPath('data.0.created_count', $rows)
            ->assertJsonPath('data.0.skipped_reasons', ['invalid_email' => 1, 'duplicate_in_file' => 0, 'previously_deleted' => 0, 'already_suppressed' => 0, 'bounced_secondary_address' => 0]);
    }

    public function test_a_chunk_job_for_an_import_that_already_ended_does_nothing(): void
    {
        $importId = $this->insertImport(['status' => 'FAILED']);
        DB::table('crm_import_rows')->insert(['import_id' => $importId, 'email' => 'late@example.com', 'reason' => 'UNSUBSCRIBED']);

        (new ProcessContactImportChunkJob($importId, null))->handle(app(ContactImportRunner::class));

        $this->assertNull($this->contact('late@example.com'));
        $this->assertSame('FAILED', DB::table('crm_imports')->where('id', $importId)->value('status'));
    }

    public function test_an_import_that_made_no_progress_for_half_an_hour_is_shown_as_failed_and_its_waiting_rows_are_dropped(): void
    {
        $stalledId = $this->insertImport(['file_name' => 'stalled.csv', 'updated_at' => now()->subMinutes(31), 'created_at' => now()->subMinutes(40)]);
        $runningId = $this->insertImport(['file_name' => 'running.csv', 'updated_at' => now()->subMinutes(29), 'created_at' => now()->subMinutes(35)]);
        $otherId = $this->insertImport(['organizer_id' => $this->otherOrganizerId, 'updated_at' => now()->subMinutes(31)]);
        foreach ([$stalledId, $runningId, $otherId] as $importId) {
            DB::table('crm_import_rows')->insert(['import_id' => $importId, 'email' => 'waiting@example.com', 'reason' => 'UNSUBSCRIBED']);
        }

        $this->crm('GET', 'imports', [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.file_name', 'running.csv')
            ->assertJsonPath('data.0.status', 'PROCESSING')
            ->assertJsonPath('data.1.file_name', 'stalled.csv')
            ->assertJsonPath('data.1.status', 'FAILED');

        $this->assertSame([$runningId, $otherId], DB::table('crm_import_rows')->orderBy('import_id')->pluck('import_id')->all());
        $this->assertSame('PROCESSING', DB::table('crm_imports')->where('id', $otherId)->value('status'));

        PruneCrmBulkOperationsJob::dispatchSync();

        $this->assertSame('FAILED', DB::table('crm_imports')->where('id', $otherId)->value('status'));
        $this->assertSame([$runningId], DB::table('crm_import_rows')->pluck('import_id')->all());
    }

    public function test_an_import_cannot_create_a_tag_past_the_limit_but_can_reuse_one(): void
    {
        $now = now();
        DB::table('crm_tags')->insert(array_map(fn (int $index) => [
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => "Tag $index",
            'created_at' => $now,
            'updated_at' => $now,
        ], range(1, UpsertTagHandler::MAX_TAGS_PER_ORGANIZER - 1)));

        $this->import("Email\ngrace@example.com\n", ['tag_name' => 'One too many'])->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->assertNull($this->contact('grace@example.com'));

        $this->import("Email\ngrace@example.com\n", ['tag_name' => 'Tag 1'])->assertCreated();
        $this->assertSame(UpsertTagHandler::MAX_TAGS_PER_ORGANIZER, DB::table('crm_tags')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_the_import_needs_a_file_and_a_short_tag_name(): void
    {
        $this->crm('POST', 'imports', [], $this->ownerHeaders())->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->import("Email\ngrace@example.com\n", ['tag_name' => str_repeat('a', 51)])->assertStatus(422)->assertJsonValidationErrors(['tag_name']);

        $this->assertNull($this->contact('grace@example.com'));
    }

    public function test_the_export_is_a_csv_of_the_organizers_contacts_without_spend(): void
    {
        DB::table('crm_contact_tags')->insert(['contact_id' => $this->contactId, 'tag_id' => $this->tagId]);

        $response = $this->crm('GET', 'contacts/export?sort_by=email&sort_direction=asc', [], $this->ownerHeaders())->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('contacts.csv', (string) $response->headers->get('Content-Disposition'));
        $rows = $this->csvRows($response);
        $this->assertSame(['Email', 'First name', 'Last name', 'Email marketing', 'Events', 'Orders', 'Tickets', 'Check-ins', 'First seen', 'Last order', 'Last check-in', 'Tags', 'Mobile number', 'Text message marketing'], $rows[0]);
        $this->assertSame(['ada@example.com', 'second@example.com'], array_column(array_slice($rows, 1), 0));
        $this->assertSame(['ada@example.com', 'Ada', 'Lovelace', 'SUBSCRIBED', '1', '1', '1', '0'], array_slice($rows[1], 0, 8));
        $this->assertSame('VIP', $rows[1][11]);
        $this->assertStringNotContainsString('other@example.com', $this->csv($response));
        $this->assertStringNotContainsString('120', $this->csv($response));
    }

    public function test_the_export_is_streamed_with_a_byte_order_mark_and_always_sorted_by_email(): void
    {
        $this->insertContact('zed@example.com', ['orders_count' => 9]);

        $response = $this->crm('GET', 'contacts/export?sort_by=orders_count&sort_direction=desc&per_page=1&page=2', [], $this->ownerHeaders())->assertOk();

        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertStringStartsWith("\xEF\xBB\xBFEmail,", $this->csv($response));
        $this->assertSame(['ada@example.com', 'second@example.com', 'zed@example.com'], array_column(array_slice($this->csvRows($response), 1), 0));
    }

    public function test_the_export_pages_through_more_contacts_than_fit_on_one_page(): void
    {
        $now = now();
        $contacts = [];
        foreach (range(1, 1001) as $index) {
            $contacts[] = [
                'account_id' => $this->accountId,
                'organizer_id' => $this->organizerId,
                'email' => sprintf('bulk%04d@example.com', $index),
                'source' => 'MANUAL',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->insertContactRows($contacts);

        $emails = array_column(array_slice($this->csvRows($this->crm('GET', 'contacts/export?query=bulk', [], $this->ownerHeaders())->assertOk()), 1), 0);

        $this->assertCount(1001, $emails);
        $this->assertSame(array_column($contacts, 'email'), $emails);
    }

    public function test_the_export_honours_the_search_and_the_segment(): void
    {
        $headers = fn () => $this->ownerHeaders();
        $emails = fn (TestResponse $response) => array_column(array_slice($this->csvRows($response), 1), 0);

        $this->assertSame(['second@example.com'], $emails($this->crm('GET', 'contacts/export?query=second', [], $headers())->assertOk()));
        $this->assertSame(['ada@example.com'], $emails($this->crm('GET', "contacts/export?segment_id={$this->segmentId}", [], $headers())->assertOk()));
        $this->assertSame(['ada@example.com'], $emails($this->crm('GET', 'contacts/export?preset=subscribers', [], $headers())->assertOk()));
        $this->assertSame(
            ['second@example.com'],
            $emails($this->crm('GET', 'contacts/export?'.http_build_query(['definition' => ['rules' => [['field' => 'orders_count', 'operator' => 'eq', 'value' => 0]]]]), [], $headers())->assertOk()),
        );
        $this->assertSame([], $emails($this->crm('GET', 'contacts/export?query=nobody', [], $headers())->assertOk()));
        $this->crm('GET', 'contacts/export?preset=everyone', [], $headers())->assertNotFound();
        $this->crm('GET', 'contacts/export?definition=orders_count', [], $headers())->assertStatus(422);
    }

    public function test_members_without_view_financials_cannot_export_a_spend_segment(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $query = http_build_query(['definition' => ['rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD']]]]);

        $this->crm('GET', "contacts/export?$query", [], $this->memberHeaders($member))->assertStatus(422);
        $this->crm('GET', 'contacts/export', [], $this->memberHeaders($member))->assertOk();
    }

    public function test_the_export_escapes_values_a_spreadsheet_would_run_as_a_formula(): void
    {
        $escaper = app(SpreadsheetFormulaEscaper::class);
        $contactId = $this->insertContact('formula@example.com', ['first_name' => '=HYPERLINK("http://evil.test","x")', 'last_name' => '+1+1']);
        DB::table('crm_contact_tags')->insert(['contact_id' => $contactId, 'tag_id' => $this->insertTag('@SUM(A1)')]);

        $rows = collect($this->csvRows($this->crm('GET', 'contacts/export?query=formula', [], $this->ownerHeaders())->assertOk()));
        $row = $rows->firstWhere(0, 'formula@example.com');

        $this->assertSame($escaper->escape('=HYPERLINK("http://evil.test","x")'), $row[1]);
        $this->assertSame($escaper->escape('+1+1'), $row[2]);
        $this->assertSame($escaper->escape('@SUM(A1)'), $row[11]);
        foreach ($row as $cell) {
            $this->assertFalse($escaper->isFormulaTrigger($cell), $cell);
        }
    }

    private function import(string $csv, array $fields = [], string $fileName = 'contacts.csv'): TestResponse
    {
        return $this->post(
            "/organizers/{$this->organizerId}/crm/imports",
            ['file' => UploadedFile::fake()->createWithContent($fileName, $csv), ...$fields],
            ['Accept' => 'application/json', ...$this->ownerHeaders()],
        );
    }

    private function insertImport(array $attributes = []): int
    {
        return DB::table('crm_imports')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'file_name' => 'contacts.csv',
            'status' => 'PROCESSING',
            'skipped_reasons' => json_encode(['invalid_email' => 0, 'duplicate_in_file' => 0, 'previously_deleted' => 0, 'already_suppressed' => 0, 'bounced_secondary_address' => 0]),
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    private function csv(TestResponse $response): string
    {
        return $response->streamedContent();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvRows(TestResponse $response): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\n/', ltrim($this->csv($response), "\xEF\xBB\xBF")), static fn (string $line) => $line !== ''));

        return array_map(static fn (string $line) => str_getcsv($line, ',', '"', ''), $lines);
    }
}
