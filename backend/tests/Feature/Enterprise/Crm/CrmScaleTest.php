<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\ProjectOrdersJob;
use HiEvents\Enterprise\Crm\Jobs\ProjectWaitlistEntriesJob;
use HiEvents\Enterprise\Crm\Jobs\ReconcileContactsJob;
use HiEvents\Enterprise\Crm\Jobs\ReconcileOrganizerContactsJob;
use HiEvents\Enterprise\Crm\Jobs\SeedContactSyncJob;
use HiEvents\Enterprise\Crm\Jobs\SendCampaignEmailJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrmScaleTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => false]);
        $this->setUpCrmOrganizer();
    }

    public function test_the_hourly_reconcile_only_queues_one_job_per_tracked_organizer(): void
    {
        Bus::fake([ReconcileOrganizerContactsJob::class]);
        $syncingOrganizerId = $this->createOrganizer($this->accountId, 'Syncing');
        $this->trackOrganizer($this->accountId, $syncingOrganizerId);
        DB::table('crm_organizer_states')->where('organizer_id', $syncingOrganizerId)->update(['synced_at' => null, 'sync_job_uuid' => (string) Str::uuid()]);
        $untouchedOrganizerId = $this->createOrganizer($this->accountId, 'Never synced');
        $this->trackOrganizer($this->accountId, $untouchedOrganizerId);
        DB::table('crm_organizer_states')->where('organizer_id', $untouchedOrganizerId)->update(['synced_at' => null]);

        ReconcileContactsJob::dispatchSync();

        $queued = Bus::dispatched(ReconcileOrganizerContactsJob::class)->map(static fn (ReconcileOrganizerContactsJob $job) => $job->organizerId);
        $this->assertContains($this->organizerId, $queued);
        $this->assertContains($syncingOrganizerId, $queued);
        $this->assertNotContains($untouchedOrganizerId, $queued);
        $this->assertSame($queued->count(), $queued->unique()->count());
    }

    public function test_changed_orders_are_paged_by_id_without_skipping_or_repeating(): void
    {
        $since = now()->subHour();
        $changed = [
            $this->insertOrder(['updated_at' => now()->subMinutes(10)]),
            $this->insertOrder(['deleted_at' => now()->subMinutes(9)]),
            $this->insertOrder(['status' => 'RESERVED', 'reserved_until' => now()->subMinutes(8)]),
        ];
        $withAttendee = $this->insertOrder();
        $this->insertAttendee($withAttendee, ['updated_at' => now()->subMinutes(7)]);
        $changed[] = $withAttendee;
        $this->insertOrder();
        $repository = app(CrmProjectionRepositoryInterface::class);

        $first = $repository->findOrderIdsChangedSince($this->organizerId, $since, now(), 0, 2);
        $second = $repository->findOrderIdsChangedSince($this->organizerId, $since, now(), end($first), 2);
        $third = $repository->findOrderIdsChangedSince($this->organizerId, $since, now(), end($second), 2);

        $this->assertSame($changed, [...$first, ...$second]);
        $this->assertSame([], $third);
    }

    public function test_the_seeder_queues_every_live_order_and_waitlist_entry_of_the_organizer_in_chunks(): void
    {
        $secondEventId = $this->createEvent($this->accountId, $this->organizerId, 'Second Event');
        $first = $this->insertOrder();
        $second = $this->insertOrder(['event_id' => $secondEventId, 'email' => 'grace@example.com']);
        $this->insertOrder(['email' => 'deleted@example.com', 'deleted_at' => now()]);
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other');
        $this->insertOrder(['event_id' => $this->createEvent($this->accountId, $otherOrganizerId, 'Foreign'), 'email' => 'foreign@example.com']);
        $entryId = $this->insertWaitlistEntry();

        [$job, $batch] = (new SeedContactSyncJob($this->accountId, $this->organizerId))->withFakeBatch();
        app()->call([$job, 'handle']);

        $orders = collect($batch->added)->filter(static fn ($added) => $added instanceof ProjectOrdersJob);
        $entries = collect($batch->added)->filter(static fn ($added) => $added instanceof ProjectWaitlistEntriesJob);
        $this->assertSame([[$first, $second]], $orders->map(static fn (ProjectOrdersJob $added) => $added->orderIds)->values()->all());
        $this->assertSame([[$entryId]], $entries->map(static fn (ProjectWaitlistEntriesJob $added) => $added->entryIds)->values()->all());
        $this->assertCount(2, $batch->added);
    }

    public function test_a_full_sync_removes_what_was_projected_from_orders_events_and_waitlist_entries_deleted_since(): void
    {
        $doomedEventId = $this->createEvent($this->accountId, $this->organizerId, 'Cancelled Event');
        $kept = $this->insertOrder(['total_gross' => 30]);
        $orderOfDeletedEvent = $this->insertOrder(['event_id' => $doomedEventId, 'total_gross' => 70]);
        $deletedOrder = $this->insertOrder(['total_gross' => 45]);
        $deletedEntry = $this->insertWaitlistEntry(['email' => 'waiting@example.com']);
        $this->projectOrders($kept, $orderOfDeletedEvent, $deletedOrder);
        $this->projectWaitlistEntries($deletedEntry);
        $this->assertSame(3, (int) $this->contact('ada@example.com')->orders_count);
        $this->assertNotNull($this->contact('waiting@example.com'));

        DB::table('events')->where('id', $doomedEventId)->update(['deleted_at' => now()]);
        DB::table('orders')->where('id', $deletedOrder)->update(['deleted_at' => now()]);
        DB::table('waitlist_entries')->where('id', $deletedEntry)->update(['deleted_at' => now()]);

        [$job, $batch] = (new SeedContactSyncJob($this->accountId, $this->organizerId))->withFakeBatch();
        app()->call([$job, 'handle']);
        foreach ($batch->added as $added) {
            app()->call([$added, 'handle']);
        }

        $contact = $this->contact('ada@example.com');
        $this->assertSame([1, 1], [(int) $contact->orders_count, (int) $contact->events_count]);
        $this->assertSame(['30.00'], DB::table('crm_contact_spend')->where('contact_id', $contact->id)->pluck('total_gross')->all());
        $this->assertNull($this->contact('waiting@example.com'));
    }

    public function test_the_seeder_stops_after_a_bounded_number_of_events_and_queues_itself_to_continue(): void
    {
        $emptyEventIds = array_map(fn (int $i) => $this->createEvent($this->accountId, $this->organizerId, "Empty $i"), range(1, 205));
        $lastOrder = $this->insertOrder(['event_id' => end($emptyEventIds), 'email' => 'last@example.com']);
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        [$job, $batch] = (new SeedContactSyncJob($this->accountId, $this->organizerId))->withFakeBatch();
        app()->call([$job, 'handle']);

        $this->assertLessThan(220, $queries);
        $continuation = collect($batch->added)->sole();
        $this->assertInstanceOf(SeedContactSyncJob::class, $continuation);
        $this->assertSame(SeedContactSyncJob::PHASE_ORDERS, $continuation->phase);
        $this->assertGreaterThan($this->eventId, $continuation->eventId);

        [$next, $nextBatch] = (new SeedContactSyncJob($this->accountId, $this->organizerId, $continuation->phase, $continuation->eventId, $continuation->afterId))->withFakeBatch();
        app()->call([$next, 'handle']);

        $this->assertSame(
            [[$lastOrder]],
            collect($nextBatch->added)->filter(static fn ($added) => $added instanceof ProjectOrdersJob)->map(static fn (ProjectOrdersJob $added) => $added->orderIds)->values()->all(),
        );
    }

    public function test_the_seeder_resumes_from_its_cursor(): void
    {
        $secondEventId = $this->createEvent($this->accountId, $this->organizerId, 'Second Event');
        $this->insertOrder();
        $skipped = $this->insertOrder(['event_id' => $secondEventId, 'email' => 'grace@example.com']);
        $resumed = $this->insertOrder(['event_id' => $secondEventId, 'email' => 'lin@example.com']);

        [$job, $batch] = (new SeedContactSyncJob($this->accountId, $this->organizerId, SeedContactSyncJob::PHASE_ORDERS, $secondEventId, $skipped))->withFakeBatch();
        app()->call([$job, 'handle']);

        $this->assertSame([[$resumed]], collect($batch->added)->filter(static fn ($added) => $added instanceof ProjectOrdersJob)->map(static fn (ProjectOrdersJob $added) => $added->orderIds)->values()->all());
    }

    public function test_a_campaign_is_queued_in_waves_and_the_next_wave_waits_for_the_last_to_drain(): void
    {
        Bus::fake([SendCampaignEmailJob::class, DispatchCampaignJob::class]);
        $total = CampaignDispatchService::WAVE_SIZE + 150;
        $campaignId = $this->insertCampaign(['status' => 'SENDING', 'sent_at' => now()]);
        DB::table('crm_campaign_recipients')->insert(array_map(fn (int $index) => [
            'campaign_id' => $campaignId,
            'organizer_id' => $this->organizerId,
            'email' => "bulk$index@example.com",
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ], range(1, $total)));
        $dispatcher = app(CampaignDispatchService::class);

        $dispatcher->dispatch($campaignId);

        Bus::assertDispatchedTimes(SendCampaignEmailJob::class, CampaignDispatchService::WAVE_SIZE);
        Bus::assertDispatched(DispatchCampaignJob::class, static fn (DispatchCampaignJob $job) => $job->campaignId === $campaignId && $job->delay !== null);

        $this->travel(CampaignDispatchService::WAVE_DELAY_SECONDS)->seconds();
        $dispatcher->dispatch($campaignId);
        Bus::assertDispatchedTimes(SendCampaignEmailJob::class, CampaignDispatchService::WAVE_SIZE);

        $firstWave = DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->orderBy('id')->limit(CampaignDispatchService::WAVE_SIZE)->pluck('id');
        DB::table('crm_campaign_recipients')->whereIn('id', $firstWave->slice(0, CampaignDispatchService::WAVE_SIZE - 50))->update(['status' => 'SENT']);
        $this->travel(CampaignDispatchService::WAVE_DELAY_SECONDS)->seconds();
        $dispatcher->dispatch($campaignId);

        Bus::assertDispatchedTimes(SendCampaignEmailJob::class, $total);
        $this->assertSame(
            $total,
            Bus::dispatched(SendCampaignEmailJob::class)->map(static fn (SendCampaignEmailJob $job) => $job->recipientId)->unique()->count(),
        );
    }
}
