<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\CrmActivitySource;
use HiEvents\Enterprise\Crm\Jobs\SyncContactsForOrderJob;
use HiEvents\Enterprise\Crm\Listeners\ContactSyncListener;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\BaseDomainEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\CheckinEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OccurrenceEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ContactSyncListenerTest extends TestCase
{
    private CrmOrganizerStateRepositoryInterface&MockInterface $states;

    private CacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        $this->states = Mockery::mock(CrmOrganizerStateRepositoryInterface::class);
        $this->cache = new CacheRepository(new ArrayStore);
    }

    public static function syncedEvents(): array
    {
        return [
            'order' => [new OrderEvent(DomainEventType::ORDER_CREATED, 11), CrmActivitySource::ORDER, 11],
            'attendee' => [new AttendeeEvent(DomainEventType::ATTENDEE_UPDATED, 22), CrmActivitySource::ATTENDEE, 22],
            'check-in' => [new CheckinEvent(DomainEventType::CHECKIN_CREATED, 33), CrmActivitySource::CHECK_IN, 33],
        ];
    }

    #[DataProvider('syncedEvents')]
    public function test_a_source_of_a_tracked_organizer_queues_a_sync_of_its_order(BaseDomainEvent $event, CrmActivitySource $source, int $sourceId): void
    {
        $this->states->shouldReceive('hasTrackedOrganizers')->andReturn(true);
        $this->states->shouldReceive('findTrackedOrderId')->once()->with($source, $sourceId)->andReturn(500);

        $this->listener(allowsCrm: true)->handle($event);

        Bus::assertDispatchedTimes(SyncContactsForOrderJob::class, 1);
        Bus::assertDispatched(SyncContactsForOrderJob::class, static fn (SyncContactsForOrderJob $job) => $job->orderId === 500);
    }

    public function test_nothing_is_queued_for_an_organizer_that_is_not_tracked(): void
    {
        $this->states->shouldReceive('hasTrackedOrganizers')->andReturn(true);
        $this->states->shouldReceive('findTrackedOrderId')->andReturn(null);

        foreach (self::syncedEvents() as [$event]) {
            $this->listener(allowsCrm: true)->handle($event);
        }

        Bus::assertNothingDispatched();
    }

    public function test_nothing_is_looked_up_while_no_organizer_is_tracked(): void
    {
        $this->states->shouldReceive('hasTrackedOrganizers')->andReturn(false);
        $this->states->shouldNotReceive('findTrackedOrderId');
        $listener = $this->listener(allowsCrm: true);

        foreach (self::syncedEvents() as [$event]) {
            $listener->handle($event);
        }

        Bus::assertNothingDispatched();
    }

    public function test_the_first_organizer_to_start_syncing_is_picked_up_straight_away(): void
    {
        $this->states->shouldReceive('hasTrackedOrganizers')->twice()->andReturn(false, true);
        $this->states->shouldReceive('findTrackedOrderId')->once()->andReturn(7);
        $listener = $this->listener(allowsCrm: true);
        $event = self::syncedEvents()['order'][0];

        $listener->handle($event);
        $listener->handle($event);

        Bus::assertDispatched(SyncContactsForOrderJob::class, static fn (SyncContactsForOrderJob $job) => $job->orderId === 7);
    }

    public function test_once_an_organizer_is_tracked_the_answer_is_cached(): void
    {
        $this->states->shouldReceive('hasTrackedOrganizers')->once()->andReturn(true);
        $this->states->shouldReceive('findTrackedOrderId')->andReturn(null);
        $listener = $this->listener(allowsCrm: true);

        foreach (self::syncedEvents() as [$event]) {
            $listener->handle($event);
        }

        Bus::assertNothingDispatched();
    }

    public function test_nothing_is_queued_without_a_crm_licence(): void
    {
        $this->states->shouldNotReceive('hasTrackedOrganizers');

        foreach (self::syncedEvents() as [$event]) {
            $this->listener(allowsCrm: false)->handle($event);
        }

        Bus::assertNothingDispatched();
    }

    public function test_events_the_crm_does_not_project_are_ignored(): void
    {
        $this->states->shouldNotReceive('findTrackedOrderId');

        $this->listener(allowsCrm: true)->handle(new OccurrenceEvent(DomainEventType::OCCURRENCE_CANCELLED, 44));

        Bus::assertNothingDispatched();
    }

    public function test_a_failure_is_reported_and_never_reaches_the_code_that_raised_the_event(): void
    {
        $failure = new RuntimeException('lookup failed');
        $this->states->shouldReceive('hasTrackedOrganizers')->andReturn(true);
        $this->states->shouldReceive('findTrackedOrderId')->andThrow($failure);

        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with($failure);
        $this->app->instance(ExceptionHandler::class, $handler);

        $this->listener(allowsCrm: true)->handle(new OrderEvent(DomainEventType::ORDER_CREATED, 11));

        Bus::assertNothingDispatched();
    }

    private function listener(bool $allowsCrm): ContactSyncListener
    {
        $licence = Mockery::mock(LicenceService::class);
        $licence->shouldReceive('allows')->with(LicensedFeature::CRM)->andReturn($allowsCrm);

        return new ContactSyncListener($licence, $this->states, $this->cache);
    }
}
