<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProjectWaitlistEntriesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WaitsForOrganizerLock;

    /**
     * @param  int[]  $entryIds
     */
    public function __construct(
        public int $accountId,
        public int $organizerId,
        public array $entryIds,
    ) {}

    public function handle(ContactSyncService $contactSyncService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (! $contactSyncService->projectWaitlistEntries($this->accountId, $this->organizerId, $this->entryIds, waitForLock: false)) {
            $this->releaseUntilOrganizerUnlocked();
        }
    }
}
