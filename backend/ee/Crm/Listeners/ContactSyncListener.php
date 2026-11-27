<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Listeners;

use HiEvents\DomainObjects\Enums\CrmActivitySource;
use HiEvents\Enterprise\Crm\Jobs\SyncContactsForOrderJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\BaseDomainEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\CheckinEvent;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

class ContactSyncListener
{
    private const ANY_TRACKED_CACHE_KEY = 'crm:any-organizer-tracked';

    private const ANY_TRACKED_CACHE_SECONDS = 300;

    public function __construct(
        private readonly LicenceService $licenceService,
        private readonly CrmOrganizerStateRepositoryInterface $organizerStateRepository,
        private readonly CacheRepository $cache,
    ) {}

    public function handle(BaseDomainEvent $event): void
    {
        try {
            if (! $this->licenceService->allows(LicensedFeature::CRM)) {
                return;
            }

            [$source, $sourceId] = match (true) {
                $event instanceof OrderEvent => [CrmActivitySource::ORDER, $event->orderId],
                $event instanceof AttendeeEvent => [CrmActivitySource::ATTENDEE, $event->attendeeId],
                $event instanceof CheckinEvent => [CrmActivitySource::CHECK_IN, $event->attendeeCheckinId],
                default => [null, null],
            };

            if ($source === null || ! $this->anyOrganizerTracked()) {
                return;
            }

            $orderId = $this->organizerStateRepository->findTrackedOrderId($source, $sourceId);

            if ($orderId !== null) {
                SyncContactsForOrderJob::dispatch($orderId)->afterCommit();
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function anyOrganizerTracked(): bool
    {
        if ($this->cache->get(self::ANY_TRACKED_CACHE_KEY) === true) {
            return true;
        }

        $tracked = $this->organizerStateRepository->hasTrackedOrganizers();

        if ($tracked) {
            $this->cache->put(self::ANY_TRACKED_CACHE_KEY, true, self::ANY_TRACKED_CACHE_SECONDS);
        }

        return $tracked;
    }
}
