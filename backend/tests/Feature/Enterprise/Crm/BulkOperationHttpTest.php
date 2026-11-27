<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\Crm\Jobs\ProcessBulkOperationChunkJob;
use HiEvents\Enterprise\Crm\Jobs\PruneCrmBulkOperationsJob;
use HiEvents\Enterprise\Crm\Services\Domain\Bulk\BulkOperationProcessor;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BulkOperationHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
    }

    public function test_tags_are_added_to_the_selected_contacts_of_the_organizer_only(): void
    {
        $response = $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'ids',
            'contact_ids' => [$this->contactId, $this->secondContactId, $this->otherContactId],
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->tagId],
        ], $this->ownerHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.processed', 2)
            ->assertJsonPath('data.changed', 2);
        $this->assertEqualsCanonicalizing([$this->contactId, $this->secondContactId], $this->taggedContactIds($this->tagId));
    }

    public function test_a_tag_of_another_organizer_is_a_validation_error_and_nothing_is_written(): void
    {
        $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'ids',
            'contact_ids' => [$this->contactId],
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->otherTagId],
        ], $this->ownerHeaders())->assertUnprocessable()->assertJsonValidationErrors('tag_ids');

        $this->assertSame(0, DB::table('crm_bulk_operations')->where('organizer_id', $this->organizerId)->where('action', 'ADD_TAGS')->where('id', '!=', $this->bulkOperationId)->count());
        $this->assertSame(0, DB::table('crm_contact_tags')->count());
    }

    public function test_a_bulk_tag_change_takes_one_tag_so_that_undo_reverses_exactly_what_it_did(): void
    {
        $secondTagId = $this->insertTag('Press');

        $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'ids',
            'contact_ids' => [$this->contactId],
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->tagId, $secondTagId],
        ], $this->ownerHeaders())->assertUnprocessable()->assertJsonValidationErrors('tag_ids');

        $this->assertSame(0, DB::table('crm_contact_tags')->count());
    }

    public function test_only_contacts_that_changed_are_counted_and_undo_reverses_just_those(): void
    {
        DB::table('crm_contact_tags')->insert(['contact_id' => $this->contactId, 'tag_id' => $this->tagId, 'created_at' => now()]);

        $operationId = $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'ids',
            'contact_ids' => [$this->contactId, $this->secondContactId],
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->tagId],
        ], $this->ownerHeaders())->assertJsonPath('data.changed', 1)->json('data.id');

        $this->crm('POST', "bulk-operations/$operationId/undo", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'UNDONE');

        $this->assertSame([$this->contactId], $this->taggedContactIds($this->tagId));
        $this->crm('POST', "bulk-operations/$operationId/undo", [], $this->ownerHeaders())->assertStatus(409);
    }

    public function test_tags_are_removed_and_the_removal_can_be_undone(): void
    {
        DB::table('crm_contact_tags')->insert([
            ['contact_id' => $this->contactId, 'tag_id' => $this->tagId, 'created_at' => now()],
            ['contact_id' => $this->secondContactId, 'tag_id' => $this->tagId, 'created_at' => now()],
        ]);

        $operationId = $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'filter',
            'definition' => ['match' => 'all', 'rules' => [['field' => 'tag', 'operator' => 'in', 'value' => [$this->tagId]]]],
            'action' => 'REMOVE_TAGS',
            'tag_ids' => [$this->tagId],
        ], $this->ownerHeaders())->assertJsonPath('data.changed', 2)->json('data.id');

        $this->assertSame([], $this->taggedContactIds($this->tagId));

        $this->crm('POST', "bulk-operations/$operationId/undo", [], $this->ownerHeaders())->assertOk();
        $this->assertEqualsCanonicalizing([$this->contactId, $this->secondContactId], $this->taggedContactIds($this->tagId));
    }

    public function test_only_the_person_who_made_the_change_can_undo_it_and_only_for_ten_minutes(): void
    {
        $operationId = $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'ids',
            'contact_ids' => [$this->contactId],
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->tagId],
        ], $this->ownerHeaders())->json('data.id');
        $colleague = $this->addAccountUser($this->accountId, Role::ADMIN);

        $this->crm('POST', "bulk-operations/$operationId/undo", [], $this->memberHeaders($colleague))->assertStatus(409);

        $this->travel(11)->minutes();
        $this->crm('POST', "bulk-operations/$operationId/undo", [], $this->ownerHeaders())->assertStatus(409);
        $this->assertSame([$this->contactId], $this->taggedContactIds($this->tagId));
    }

    public function test_filter_mode_selects_every_contact_matching_the_search_and_segment(): void
    {
        $this->insertContact('ada.lovelace@example.com', ['orders_count' => 4]);
        $this->insertContact('ada.byron@example.com', ['orders_count' => 0]);

        $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'filter',
            'query' => 'ada.',
            'definition' => ['match' => 'all', 'rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 1]]],
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->tagId],
        ], $this->ownerHeaders())->assertJsonPath('data.total', 1);

        $this->assertSame([$this->contactId('ada.lovelace@example.com')], $this->taggedContactIds($this->tagId));
    }

    public function test_a_member_without_view_financials_cannot_select_by_spend(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);

        $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'filter',
            'definition' => ['match' => 'all', 'rules' => [['field' => 'total_spent', 'operator' => 'gte', 'value' => 50, 'currency' => 'USD']]],
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->tagId],
        ], $this->memberHeaders($member))->assertUnprocessable();

        $this->assertSame([], $this->taggedContactIds($this->tagId));
    }

    public function test_selected_ids_must_be_unique_and_are_limited(): void
    {
        $this->crm('POST', 'contacts/bulk/unsubscribe', [
            'mode' => 'ids',
            'contact_ids' => [$this->contactId, $this->contactId],
            'confirmation_count' => 1,
        ], $this->ownerHeaders())->assertUnprocessable()->assertJsonValidationErrors('contact_ids');

        $this->crm('POST', 'contacts/bulk/unsubscribe', [
            'mode' => 'ids',
            'contact_ids' => range(1, 1001),
        ], $this->ownerHeaders())->assertUnprocessable()->assertJsonValidationErrors('contact_ids');
    }

    public function test_unsubscribing_records_an_opt_out_for_a_contact_who_never_subscribed_so_a_later_opt_in_from_the_past_cannot_subscribe_them(): void
    {
        $orderId = $this->insertOrder(['email' => 'never@example.com', 'first_name' => 'Nell', 'last_name' => 'Never']);
        $this->projectOrders($orderId);
        $neverId = $this->contactId('never@example.com');
        $this->assertSame('NOT_SUBSCRIBED', $this->contact('never@example.com')->email_marketing_status);

        $this->crm('POST', 'contacts/bulk/unsubscribe', [
            'mode' => 'ids',
            'contact_ids' => [$neverId],
            'confirmation_count' => 1,
        ], $this->ownerHeaders())->assertCreated()->assertJsonPath('data.changed', 1);

        $this->assertSame('UNSUBSCRIBED', $this->contact('never@example.com')->email_marketing_status);
        $this->assertSame(
            ['email' => 'never@example.com', 'source_type' => 'MANUAL', 'user_id' => $this->owner->id],
            (array) DB::table('crm_consent_events')->where('contact_id', $neverId)->where('action', 'OPT_OUT')->sole(['email', 'source_type', 'user_id']),
        );

        DB::table('orders')->where('id', $orderId)->update(['opted_into_marketing_at' => now()->subDays(5)]);
        $this->projectOrders($orderId);

        $this->assertSame('UNSUBSCRIBED', $this->contact('never@example.com')->email_marketing_status);
    }

    public function test_unsubscribing_opts_out_subscribed_contacts_and_leaves_already_opted_out_ones_alone(): void
    {
        $subscribedId = $this->insertSubscribedContact('fan@example.com');
        $unsubscribedId = $this->insertContact('left@example.com', ['email_marketing_status' => 'UNSUBSCRIBED']);
        $complainedId = $this->insertContact('angry@example.com', ['email_marketing_status' => 'COMPLAINED']);

        $this->crm('POST', 'contacts/bulk/unsubscribe', [
            'mode' => 'ids',
            'contact_ids' => [$subscribedId, $unsubscribedId, $complainedId],
            'confirmation_count' => 3,
        ], $this->ownerHeaders())->assertCreated()->assertJsonPath('data.changed', 1);

        $this->assertSame('UNSUBSCRIBED', DB::table('crm_contacts')->where('id', $subscribedId)->value('email_marketing_status'));
        $this->assertSame(
            ['email' => 'fan@example.com', 'source_type' => 'MANUAL', 'user_id' => $this->owner->id],
            (array) DB::table('crm_consent_events')->where('contact_id', $subscribedId)->where('action', 'OPT_OUT')->first(['email', 'source_type', 'user_id']),
        );
        $this->assertSame(0, DB::table('crm_consent_events')->whereIn('contact_id', [$unsubscribedId, $complainedId])->where('action', 'OPT_OUT')->count());
    }

    public function test_unsubscribing_a_bounced_contact_keeps_them_unsubscribed_once_the_bounce_is_cleared(): void
    {
        $bouncedId = $this->insertSubscribedContact('gone@example.com');
        app(ConsentRecorder::class)->recordForEmail($this->organizerId, $bouncedId, 'gone@example.com', CrmConsentAction::BOUNCED, CrmConsentSource::MANUAL);
        $this->assertSame('BOUNCED', DB::table('crm_contacts')->where('id', $bouncedId)->value('email_marketing_status'));
        $unsubscribe = fn () => $this->crm('POST', 'contacts/bulk/unsubscribe', [
            'mode' => 'ids',
            'contact_ids' => [$bouncedId],
            'confirmation_count' => 1,
        ], $this->ownerHeaders())->assertCreated();

        $unsubscribe()->assertJsonPath('data.changed', 1);
        $unsubscribe()->assertJsonPath('data.changed', 0);

        $this->assertSame('BOUNCED', DB::table('crm_contacts')->where('id', $bouncedId)->value('email_marketing_status'));
        $this->assertSame(1, DB::table('crm_consent_events')->where('contact_id', $bouncedId)->where('action', 'OPT_OUT')->count());

        app(ConsentRecorder::class)->recordForEmail($this->organizerId, $bouncedId, 'gone@example.com', CrmConsentAction::BOUNCE_CLEARED, CrmConsentSource::MANUAL);

        $this->assertSame('UNSUBSCRIBED', DB::table('crm_contacts')->where('id', $bouncedId)->value('email_marketing_status'));
    }

    public function test_a_destructive_action_whose_selection_changed_is_refused_with_the_new_count(): void
    {
        $before = $this->crmState();

        $this->crm('POST', 'contacts/bulk/delete', [
            'mode' => 'filter',
            'confirmation_count' => 1,
        ], $this->ownerHeaders())
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'SELECTION_CHANGED')
            ->assertJsonPath('total', 2);

        $this->assertSame($before, $this->crmState());
    }

    public function test_deleting_contacts_in_bulk_matches_deleting_them_one_by_one(): void
    {
        $recipientId = $this->insertRecipient($this->campaignId, $this->contactId, 'ada@example.com', ['status' => 'SENT']);
        $this->insertConsent($this->contactId, 'ada@example.com', 'OPT_OUT', now());

        $this->crm('POST', 'contacts/bulk/delete', [
            'mode' => 'ids',
            'contact_ids' => [$this->contactId, $this->secondContactId],
            'confirmation_count' => 2,
        ], $this->ownerHeaders())->assertCreated()->assertJsonPath('data.changed', 2);

        $this->assertSame(0, DB::table('crm_contacts')->whereIn('id', [$this->contactId, $this->secondContactId])->count());
        $this->assertSame(3, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->count());
        $this->assertSame(2, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->where('suppression', 'UNSUBSCRIBED')->count());
        $recipient = DB::table('crm_campaign_recipients')->where('id', $recipientId)->first();
        $this->assertNull($recipient->contact_id);
        $this->assertStringStartsWith('deleted:', $recipient->email);
        $this->assertSame(1, DB::table('orders')->where('id', $this->orderId)->count());
        $this->assertSame(1, DB::table('crm_contacts')->where('id', $this->otherContactId)->count());
    }

    public function test_a_destructive_action_that_stopped_making_progress_no_longer_blocks_the_next_one(): void
    {
        $stalledId = $this->insertBulkOperation($this->organizerId, ['action' => 'DELETE', 'status' => 'RUNNING', 'tag_ids' => null, 'total' => 900, 'updated_at' => now()->subMinutes(31)]);

        $this->crm('POST', 'contacts/bulk/unsubscribe', [
            'mode' => 'ids',
            'contact_ids' => [$this->contactId],
            'confirmation_count' => 1,
        ], $this->ownerHeaders())->assertSuccessful();

        $this->assertSame('FAILED', DB::table('crm_bulk_operations')->where('id', $stalledId)->value('status'));
    }

    public function test_a_second_destructive_action_waits_for_the_first_to_finish(): void
    {
        $this->insertBulkOperation($this->organizerId, ['action' => 'DELETE', 'status' => 'RUNNING', 'tag_ids' => null, 'total' => 900]);

        $this->crm('POST', 'contacts/bulk/unsubscribe', [
            'mode' => 'ids',
            'contact_ids' => [$this->contactId],
            'confirmation_count' => 1,
        ], $this->ownerHeaders())->assertStatus(409);

        $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'ids',
            'contact_ids' => [$this->contactId],
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->tagId],
        ], $this->ownerHeaders())->assertCreated();
    }

    public function test_a_large_selection_is_processed_one_queued_chunk_at_a_time_and_completes(): void
    {
        Bus::fake([ProcessBulkOperationChunkJob::class]);
        $now = now();
        $total = BulkOperationProcessor::CHUNK_SIZE * 2 + 10;
        $this->insertContactRows(array_map(fn (int $i) => [
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'email' => "bulk$i@example.com",
            'source' => 'IMPORT',
            'created_at' => $now,
            'updated_at' => $now,
        ], range(1, $total)));

        $operationId = $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'filter',
            'query' => 'bulk',
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->tagId],
        ], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.status', 'RUNNING')
            ->assertJsonPath('data.processed', 0)
            ->json('data.id');

        Bus::assertDispatchedTimes(ProcessBulkOperationChunkJob::class, 1);

        $handled = 0;
        while (($job = Bus::dispatched(ProcessBulkOperationChunkJob::class)->get($handled)) !== null) {
            $this->assertCount($handled + 1, Bus::dispatched(ProcessBulkOperationChunkJob::class));
            app()->call([$job, 'handle']);
            $handled++;
        }

        $this->assertSame(3, $handled);
        $this->crm('GET', "bulk-operations/$operationId", [], $this->ownerHeaders())
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.processed', $total)
            ->assertJsonPath('data.changed', $total);
        $this->assertCount($total, $this->taggedContactIds($this->tagId));
    }

    public function test_a_retried_chunk_does_not_reapply_contacts_it_already_claimed(): void
    {
        Bus::fake([ProcessBulkOperationChunkJob::class]);
        $now = now();
        $total = BulkOperationProcessor::CHUNK_SIZE + 10;
        $this->insertContactRows(array_map(fn (int $i) => [
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'email' => "bulk$i@example.com",
            'source' => 'IMPORT',
            'created_at' => $now,
            'updated_at' => $now,
        ], range(1, $total)));

        $operationId = $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'filter',
            'query' => 'bulk',
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->tagId],
        ], $this->ownerHeaders())->assertCreated()->json('data.id');

        $first = Bus::dispatched(ProcessBulkOperationChunkJob::class)->first();
        app()->call([$first, 'handle']);
        app()->call([$first, 'handle']);

        $this->crm('GET', "bulk-operations/$operationId", [], $this->ownerHeaders())
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.processed', $total);
        $this->assertCount($total, $this->taggedContactIds($this->tagId));
    }

    public function test_a_chunk_that_fails_for_good_marks_the_operation_failed(): void
    {
        $operationId = $this->insertBulkOperation($this->organizerId, ['status' => 'RUNNING', 'total' => 900]);

        (new ProcessBulkOperationChunkJob($operationId, 0))->failed(null);

        $this->assertSame('FAILED', DB::table('crm_bulk_operations')->where('id', $operationId)->value('status'));
    }

    public function test_a_tag_operation_that_stopped_making_progress_is_reported_as_failed(): void
    {
        $stalledId = $this->insertBulkOperation($this->organizerId, ['status' => 'RUNNING', 'total' => 900, 'updated_at' => now()->subMinutes(31)]);
        $activeId = $this->insertBulkOperation($this->organizerId, ['status' => 'RUNNING', 'total' => 900, 'updated_at' => now()->subMinutes(5)]);

        $this->crm('GET', "bulk-operations/$stalledId", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'FAILED');
        $this->crm('GET', "bulk-operations/$activeId", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'RUNNING');
    }

    public function test_unsubscribing_and_deleting_cannot_be_undone(): void
    {
        $subscribedId = $this->insertSubscribedContact('fan@example.com');
        $unsubscribeId = $this->crm('POST', 'contacts/bulk/unsubscribe', ['mode' => 'ids', 'contact_ids' => [$subscribedId], 'confirmation_count' => 1], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->json('data.id');
        $deleteId = $this->crm('POST', 'contacts/bulk/delete', ['mode' => 'ids', 'contact_ids' => [$this->secondContactId], 'confirmation_count' => 1], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->json('data.id');

        $this->crm('POST', "bulk-operations/$unsubscribeId/undo", [], $this->ownerHeaders())->assertStatus(409);
        $this->crm('POST', "bulk-operations/$deleteId/undo", [], $this->ownerHeaders())->assertStatus(409);

        $this->assertSame('UNSUBSCRIBED', DB::table('crm_contacts')->where('id', $subscribedId)->value('email_marketing_status'));
        $this->assertNull(DB::table('crm_contacts')->where('id', $this->secondContactId)->first());
        $this->assertSame(['COMPLETED', 'COMPLETED'], DB::table('crm_bulk_operations')->whereIn('id', [$unsubscribeId, $deleteId])->orderBy('id')->pluck('status')->all());
    }

    public function test_a_tag_change_that_is_still_running_or_was_already_undone_cannot_be_undone(): void
    {
        DB::table('crm_contact_tags')->insert(['contact_id' => $this->contactId, 'tag_id' => $this->tagId, 'created_at' => now()]);
        $runningId = $this->insertBulkOperation($this->organizerId, ['status' => 'RUNNING', 'total' => 900]);
        DB::table('crm_bulk_operation_contacts')->insert(['operation_id' => $runningId, 'contact_id' => $this->contactId, 'changed' => true]);
        $doneId = $this->crm('POST', 'contacts/bulk/tags', ['mode' => 'ids', 'contact_ids' => [$this->secondContactId], 'action' => 'ADD_TAGS', 'tag_ids' => [$this->tagId]], $this->ownerHeaders())->json('data.id');

        $this->crm('POST', "bulk-operations/$runningId/undo", [], $this->ownerHeaders())->assertStatus(409);
        $this->crm('POST', "bulk-operations/$doneId/undo", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'UNDONE');
        $this->crm('POST', "bulk-operations/$doneId/undo", [], $this->ownerHeaders())->assertStatus(409);

        $this->assertSame([$this->contactId], $this->taggedContactIds($this->tagId));
    }

    #[DataProvider('queuedDestructiveActions')]
    public function test_a_large_unsubscribe_or_delete_is_confirmed_against_the_snapshot_and_finished_by_queued_chunks(string $action): void
    {
        Bus::fake([ProcessBulkOperationChunkJob::class]);
        $now = now();
        $total = BulkOperationProcessor::CHUNK_SIZE + 10;
        $this->insertContactRows(array_map(fn (int $i) => [
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'email' => "bulk$i@example.com",
            'email_marketing_status' => 'SUBSCRIBED',
            'source' => 'IMPORT',
            'created_at' => $now,
            'updated_at' => $now,
        ], range(1, $total)));
        DB::statement("
            INSERT INTO crm_consent_events (organizer_id, contact_id, email, action, source_type, occurred_at)
            SELECT organizer_id, id, email, 'OPT_IN', 'MANUAL', now() - interval '30 days' FROM crm_contacts WHERE email LIKE 'bulk%'
        ");
        $selection = ['mode' => 'filter', 'query' => 'bulk'];

        $this->crm('POST', "contacts/bulk/$action", [...$selection, 'confirmation_count' => $total - 1], $this->ownerHeaders())
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'SELECTION_CHANGED')
            ->assertJsonPath('total', $total);
        Bus::assertNotDispatched(ProcessBulkOperationChunkJob::class);

        $operationId = $this->crm('POST', "contacts/bulk/$action", [...$selection, 'confirmation_count' => $total], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.status', 'RUNNING')
            ->assertJsonPath('data.processed', 0)
            ->json('data.id');
        $this->assertSame($total, DB::table('crm_contacts')->where('email', 'like', 'bulk%')->where('email_marketing_status', 'SUBSCRIBED')->count());

        $handled = 0;
        while (($job = Bus::dispatched(ProcessBulkOperationChunkJob::class)->get($handled)) !== null) {
            $this->assertCount($handled + 1, Bus::dispatched(ProcessBulkOperationChunkJob::class));
            app()->call([$job, 'handle']);
            $handled++;
        }

        $this->assertSame(2, $handled);
        $this->crm('GET', "bulk-operations/$operationId", [], $this->ownerHeaders())
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.processed', $total)
            ->assertJsonPath('data.changed', $total);
        $remaining = DB::table('crm_contacts')->where('email', 'like', 'bulk%');
        if ($action === 'delete') {
            $this->assertSame(0, $remaining->count());
            $this->assertSame($total, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->count());
        } else {
            $this->assertSame($total, $remaining->where('email_marketing_status', 'UNSUBSCRIBED')->count());
        }
        $this->assertNotNull(DB::table('crm_contacts')->where('id', $this->contactId)->first());
    }

    public static function queuedDestructiveActions(): array
    {
        return [
            'unsubscribe' => ['unsubscribe'],
            'delete' => ['delete'],
        ];
    }

    public function test_selection_snapshots_are_pruned_a_week_after_their_operation_finished_and_the_operation_is_kept(): void
    {
        $snapshot = function (array $attributes): int {
            $operationId = $this->insertBulkOperation($this->organizerId, ['total' => 1, ...$attributes]);
            DB::table('crm_bulk_operation_contacts')->insert(['operation_id' => $operationId, 'contact_id' => $this->contactId, 'processed' => true, 'changed' => true]);

            return $operationId;
        };
        $oldId = $snapshot(['finished_at' => now()->subDays(8)]);
        $recentId = $snapshot(['finished_at' => now()->subDays(6)]);
        $runningId = $snapshot(['status' => 'RUNNING', 'finished_at' => null, 'created_at' => now()->subDays(30)]);

        PruneCrmBulkOperationsJob::dispatchSync();

        $this->assertSame(
            [$recentId, $runningId],
            DB::table('crm_bulk_operation_contacts')->orderBy('operation_id')->pluck('operation_id')->map(static fn ($id) => (int) $id)->all(),
        );
        $this->assertSame(3, DB::table('crm_bulk_operations')->whereIn('id', [$oldId, $recentId, $runningId])->count());
    }

    public function test_an_operation_nobody_is_watching_is_failed_by_the_daily_job_and_its_snapshot_pruned_a_week_later(): void
    {
        $stalledId = $this->insertBulkOperation($this->organizerId, ['status' => 'RUNNING', 'total' => 900, 'updated_at' => now()->subMinutes(31)]);
        $activeId = $this->insertBulkOperation($this->organizerId, ['status' => 'RUNNING', 'total' => 900, 'updated_at' => now()->subMinutes(5)]);
        DB::table('crm_bulk_operation_contacts')->insert([
            ['operation_id' => $stalledId, 'contact_id' => $this->contactId],
            ['operation_id' => $activeId, 'contact_id' => $this->contactId],
        ]);

        PruneCrmBulkOperationsJob::dispatchSync();

        $this->assertSame(
            [$stalledId => 'FAILED', $activeId => 'RUNNING'],
            DB::table('crm_bulk_operations')->whereIn('id', [$stalledId, $activeId])->orderBy('id')->pluck('status', 'id')->all(),
        );
        $this->assertNotNull(DB::table('crm_bulk_operations')->where('id', $stalledId)->value('finished_at'));
        $this->assertSame(2, DB::table('crm_bulk_operation_contacts')->whereIn('operation_id', [$stalledId, $activeId])->count());

        $this->travel(8)->days();
        DB::table('crm_bulk_operations')->where('id', $activeId)->update(['updated_at' => now()]);
        PruneCrmBulkOperationsJob::dispatchSync();

        $this->assertSame([$activeId], DB::table('crm_bulk_operation_contacts')->pluck('operation_id')->map(static fn ($id) => (int) $id)->all());
    }

    public function test_a_backlog_of_old_snapshots_is_pruned_a_batch_at_a_time_until_none_are_left(): void
    {
        $oldId = $this->insertBulkOperation($this->organizerId, ['finished_at' => now()->subDays(8)]);
        DB::insert(
            'INSERT INTO crm_bulk_operation_contacts (operation_id, contact_id, processed) SELECT ?, n, true FROM generate_series(1, ?) AS n',
            [$oldId, PruneCrmBulkOperationsJob::SNAPSHOT_ROWS_PER_RUN + 10],
        );

        PruneCrmBulkOperationsJob::dispatchSync();

        $this->assertSame(0, DB::table('crm_bulk_operation_contacts')->where('operation_id', $oldId)->count());
    }

    public function test_a_queued_tag_change_can_be_undone_for_ten_minutes_after_it_finished_however_long_it_took(): void
    {
        DB::table('crm_contact_tags')->insert(['contact_id' => $this->contactId, 'tag_id' => $this->tagId, 'created_at' => now()]);
        $slow = fn (array $attributes) => $this->insertBulkOperation($this->organizerId, ['total' => 1, 'changed' => 1, 'created_at' => now()->subMinutes(45), ...$attributes]);
        $justFinishedId = $slow(['finished_at' => now()->subMinute()]);
        $longFinishedId = $slow(['finished_at' => now()->subMinutes(11)]);
        DB::table('crm_bulk_operation_contacts')->insert([
            ['operation_id' => $justFinishedId, 'contact_id' => $this->contactId, 'processed' => true, 'changed' => true],
            ['operation_id' => $longFinishedId, 'contact_id' => $this->contactId, 'processed' => true, 'changed' => true],
        ]);

        $this->crm('POST', "bulk-operations/$longFinishedId/undo", [], $this->ownerHeaders())->assertStatus(409);
        $this->assertSame([$this->contactId], $this->taggedContactIds($this->tagId));

        $this->crm('POST', "bulk-operations/$justFinishedId/undo", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'UNDONE');
        $this->assertSame([], $this->taggedContactIds($this->tagId));
    }

    public function test_undoing_a_large_tag_change_reverses_every_contact_it_changed_and_nobody_else(): void
    {
        $alreadyTaggedId = $this->insertContact('bulk-already@example.com');
        $changedIds = array_map(fn (int $index) => $this->insertContact("bulk-$index@example.com"), range(1, BulkOperationProcessor::CHUNK_SIZE + 5));
        DB::table('crm_contact_tags')->insert(['contact_id' => $alreadyTaggedId, 'tag_id' => $this->tagId, 'created_at' => now()]);

        $operationId = $this->crm('POST', 'contacts/bulk/tags', [
            'mode' => 'filter',
            'query' => 'bulk-',
            'action' => 'ADD_TAGS',
            'tag_ids' => [$this->tagId],
        ], $this->ownerHeaders())->assertCreated()->json('data.id');
        $this->assertSame('COMPLETED', DB::table('crm_bulk_operations')->where('id', $operationId)->value('status'));
        $this->assertGreaterThan(BulkOperationProcessor::CHUNK_SIZE, count($this->taggedContactIds($this->tagId)));

        $this->crm('POST', "bulk-operations/$operationId/undo", [], $this->ownerHeaders())->assertOk()->assertJsonPath('data.status', 'UNDONE');

        $this->assertSame([$alreadyTaggedId], $this->taggedContactIds($this->tagId));
        $this->assertSame([], array_intersect($changedIds, $this->taggedContactIds($this->tagId)));
    }

    public function test_another_organizers_operation_is_not_found(): void
    {
        $operationId = $this->insertBulkOperation($this->otherOrganizerId);

        $this->crm('GET', "bulk-operations/$operationId", [], $this->ownerHeaders())->assertNotFound();
        $this->crm('POST', "bulk-operations/$operationId/undo", [], $this->ownerHeaders())->assertNotFound();
    }

    public function test_the_selected_contacts_can_be_exported(): void
    {
        $response = $this->crm('POST', 'contacts/export', ['contact_ids' => [$this->secondContactId, $this->otherContactId]], $this->ownerHeaders());

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('second@example.com', $csv);
        $this->assertStringNotContainsString('ada@example.com', $csv);
        $this->assertStringNotContainsString('other@example.com', $csv);
    }

    /**
     * @return int[]
     */
    private function taggedContactIds(int $tagId): array
    {
        return DB::table('crm_contact_tags')->where('tag_id', $tagId)->orderBy('contact_id')->pluck('contact_id')->map(static fn ($id) => (int) $id)->all();
    }
}
