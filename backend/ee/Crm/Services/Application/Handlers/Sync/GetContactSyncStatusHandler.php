<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Sync;

use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sync\DTO\ContactSyncStatusDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncStatusService;

class GetContactSyncStatusHandler
{
    public function __construct(
        private readonly ContactSyncStatusService $syncStatusService,
    ) {}

    public function handle(int $organizerId): ContactSyncStatusDTO
    {
        return $this->syncStatusService->getStatus($organizerId);
    }
}
