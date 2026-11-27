<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncContactsForOrderJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WaitsForOrganizerLock;

    public int $uniqueFor = 300;

    public function __construct(
        public int $orderId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function handle(ContactSyncService $contactSyncService): void
    {
        if (! $contactSyncService->syncOrder($this->orderId, waitForLock: false)) {
            $this->releaseUntilOrganizerUnlocked();
        }
    }
}
