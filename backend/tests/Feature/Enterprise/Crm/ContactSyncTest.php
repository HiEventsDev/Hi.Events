<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Closure;
use HiEvents\Enterprise\Crm\Jobs\ProjectOrdersJob;
use HiEvents\Enterprise\Crm\Jobs\SeedContactSyncJob;
use HiEvents\Enterprise\Crm\Jobs\SyncContactsForOrderJob;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sync\StartContactSyncHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactReconcileService;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncStatusService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\CheckinEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ContactSyncTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmOrganizer();
    }

    public static function domainEvents(): array
    {
        return [
            'an order' => [static fn (int $orderId, int $attendeeId, int $checkInId) => new OrderEvent(DomainEventType::ORDER_CREATED, $orderId)],
            'an attendee' => [static fn (int $orderId, int $attendeeId, int $checkInId) => new AttendeeEvent(DomainEventType::ATTENDEE_UPDATED, $attendeeId)],
            'a check-in' => [static fn (int $orderId, int $attendeeId, int $checkInId) => new CheckinEvent(DomainEventType::CHECKIN_CREATED, $checkInId)],
        ];
    }

    #[DataProvider('domainEvents')]
    public function test_a_domain_event_queues_a_sync_of_its_order_after_commit_only_while_the_organizer_is_tracked(Closure $event): void
    {
        Bus::fake([SyncContactsForOrderJob::class]);
        $orderId = $this->insertOrder();
        $attendeeId = $this->insertAttendee($orderId);
        $checkInId = $this->insertCheckIn($attendeeId);

        event($event($orderId, $attendeeId, $checkInId));

        Bus::assertDispatchedTimes(SyncContactsForOrderJob::class, 1);
        Bus::assertDispatched(SyncContactsForOrderJob::class, static fn (SyncContactsForOrderJob $job) => $job->orderId === $orderId && $job->afterCommit === true);

        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->delete();
        event($event($orderId, $attendeeId, $checkInId));

        Bus::assertDispatchedTimes(SyncContactsForOrderJob::class, 1);
    }

    public function test_an_order_event_updates_the_contact_once_its_transaction_commits_and_never_when_it_rolls_back(): void
    {
        $orderId = $this->insertOrder();

        try {
            DB::transaction(static function () use ($orderId): void {
                event(new OrderEvent(DomainEventType::ORDER_CREATED, $orderId));

                throw new RuntimeException('The checkout failed.');
            });
        } catch (RuntimeException) {
        }

        $this->assertNull($this->contact('ada@example.com'));

        DB::transaction(function () use ($orderId): void {
            event(new OrderEvent(DomainEventType::ORDER_CREATED, $orderId));

            $this->assertNull($this->contact('ada@example.com'));
        });

        $this->assertSame(1, (int) $this->contact('ada@example.com')->orders_count);
    }

    public function test_a_reconcile_window_too_long_to_replay_queues_a_full_sync_instead(): void
    {
        Bus::fake();
        $longAgo = now()->subDays(40)->startOfSecond();
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->update(['synced_at' => $longAgo, 'reconciled_until' => $longAgo]);
        $this->insertOrder(['updated_at' => now()->subDays(20)]);

        $this->assertNull(app(ContactReconcileService::class)->reconcile($this->organizerId, null));

        Bus::assertNotDispatched(ProjectOrdersJob::class);
        Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === 'crm-contact-sync-'.$this->organizerId
            && $batch->jobs->sole() instanceof SeedContactSyncJob);
        $state = DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->sole();
        $this->assertNotNull($state->sync_job_uuid);
        $this->assertSame($longAgo->toDateTimeString(), $state->reconciled_until);
    }

    public function test_a_catch_up_sync_moves_the_reconcile_point_only_once_it_finishes(): void
    {
        $longAgo = now()->subDays(40)->startOfSecond();
        $this->freezeSecond();
        $uuid = (string) Str::uuid();
        DB::table('job_batches')->insert([
            'id' => $uuid,
            'name' => 'crm-contact-sync-'.$this->organizerId,
            'total_jobs' => 1,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => serialize([]),
            'created_at' => now()->subMinutes(5)->getTimestamp(),
            'finished_at' => now()->getTimestamp(),
        ]);
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->update(['synced_at' => $longAgo, 'reconciled_until' => $longAgo, 'sync_job_uuid' => $uuid]);

        $this->assertSame('READY', app(ContactSyncStatusService::class)->getStatus($this->organizerId)->status);
        $this->assertSame($longAgo->toDateTimeString(), DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->value('reconciled_until'));

        app(ContactReconcileService::class)->reconcile($this->organizerId, null);

        $this->assertSame(
            now()->subMinutes(5)->subSeconds(ContactReconcileService::SETTLE_SECONDS)->toDateTimeString(),
            DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->value('reconciled_until'),
        );
    }

    public function test_a_failed_re_sync_stays_failed_without_writing_until_it_is_retried(): void
    {
        Bus::fake();
        $this->insertOrder();
        $uuid = (string) Str::uuid();
        DB::table('job_batches')->insert([
            'id' => $uuid,
            'name' => 'crm-contact-sync-'.$this->organizerId,
            'total_jobs' => 3,
            'pending_jobs' => 2,
            'failed_jobs' => 1,
            'failed_job_ids' => '[]',
            'options' => serialize([]),
            'created_at' => now()->getTimestamp(),
        ]);
        $longAgo = now()->subDays(40)->startOfSecond();
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->update(['sync_job_uuid' => $uuid, 'reconciled_until' => $longAgo]);
        $status = app(ContactSyncStatusService::class);

        $this->assertSame('FAILED', $status->getStatus($this->organizerId)->status);
        $this->assertSame('FAILED', $status->getStatus($this->organizerId)->status);
        $this->assertSame($uuid, DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->value('sync_job_uuid'));
        $this->assertSame($longAgo->toDateTimeString(), DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->value('reconciled_until'));

        $this->assertSame('SYNCING', app(StartContactSyncHandler::class)->handle($this->accountId, $this->organizerId)->status);
        $this->assertNotSame($uuid, DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->value('sync_job_uuid'));
    }

    public function test_reading_the_status_of_a_finished_sync_writes_nothing_and_the_sync_marks_itself_done(): void
    {
        $this->freezeSecond();
        $uuid = (string) Str::uuid();
        DB::table('job_batches')->insert([
            'id' => $uuid,
            'name' => 'crm-contact-sync-'.$this->organizerId,
            'total_jobs' => 1,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => serialize([]),
            'created_at' => now()->subMinutes(5)->getTimestamp(),
            'finished_at' => now()->getTimestamp(),
        ]);
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->update(['synced_at' => null, 'sync_job_uuid' => $uuid]);

        $this->assertSame('READY', app(ContactSyncStatusService::class)->getStatus($this->organizerId)->status);
        $this->assertSame($uuid, DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->value('sync_job_uuid'));

        $stateId = (int) DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->value('id');
        app(ContactSyncStatusService::class)->markSynced($stateId, now()->subMinutes(5), $uuid);

        $state = DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->sole();
        $this->assertNull($state->sync_job_uuid);
        $this->assertSame(now()->toDateTimeString(), $state->synced_at);
    }

    public function test_the_status_counts_include_a_contact_projected_since_the_last_read(): void
    {
        $before = app(ContactSyncStatusService::class)->getStatus($this->organizerId);
        $this->assertSame(0, $before->contacts_count);

        $this->projectOrders($this->insertOrder(['opted_into_marketing_at' => now()->subDay()]));

        $after = app(ContactSyncStatusService::class)->getStatus($this->organizerId);
        $this->assertSame(1, $after->contacts_count);
        $this->assertSame(1, $after->mailable_count);
    }

    public function test_a_sync_marks_the_organizer_synced_when_its_batch_completes(): void
    {
        Bus::fake();
        $this->freezeSecond();
        $this->insertOrder();
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->update(['synced_at' => null]);

        app(StartContactSyncHandler::class)->handle($this->accountId, $this->organizerId);

        $pending = null;
        Bus::assertBatched(function (PendingBatch $batch) use (&$pending): bool {
            $pending = $batch;

            return true;
        });
        $batch = app(BatchRepository::class)->store($pending);
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->update(['sync_job_uuid' => $batch->id]);

        $pending->thenCallbacks()[0]($batch);

        $state = DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->sole();
        $this->assertNull($state->sync_job_uuid);
        $this->assertSame(now()->toDateTimeString(), $state->synced_at);
    }
}
