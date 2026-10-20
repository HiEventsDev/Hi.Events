<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\Services\Infrastructure\Lock\TransactionLockService;

class SeatingEventLockService
{
    public function __construct(
        private readonly TransactionLockService $transactionLockService,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
        private readonly SeatedProductLookupService $seatedProductLookup,
    ) {}

    public function lock(int $eventId): void
    {
        $this->transactionLockService->lockEvent($eventId);
        $this->eventSeatMapLookup->forget($eventId);
        $this->seatedProductLookup->forget();
    }
}
