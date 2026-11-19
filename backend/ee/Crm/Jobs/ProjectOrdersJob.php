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

class ProjectOrdersJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WaitsForOrganizerLock;

    /**
     * @param  int[]  $orderIds
     */
    public function __construct(
        public int $accountId,
        public int $organizerId,
        public array $orderIds,
    ) {}

    public function handle(ContactSyncService $contactSyncService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (! $contactSyncService->projectOrders($this->accountId, $this->organizerId, $this->orderIds, waitForLock: false)) {
            $this->releaseUntilOrganizerUnlocked();
        }
    }
}
