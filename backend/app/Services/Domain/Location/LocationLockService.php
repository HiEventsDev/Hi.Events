<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Location;

use Illuminate\Database\DatabaseManager;

class LocationLockService
{
    public const LOCATIONS_LOCK_KEYSPACE = 0x4C4F43;

    public const MAX_LOCK_KEY = 2147483647;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function acquireSharedTransactionLock(int $locationId): void
    {
        $this->databaseManager->statement(
            'SELECT pg_advisory_xact_lock_shared(?, ?)',
            [self::LOCATIONS_LOCK_KEYSPACE, $locationId % self::MAX_LOCK_KEY],
        );
    }

    public function acquireExclusiveTransactionLock(int $locationId): void
    {
        $this->databaseManager->statement(
            'SELECT pg_advisory_xact_lock(?, ?)',
            [self::LOCATIONS_LOCK_KEYSPACE, $locationId % self::MAX_LOCK_KEY],
        );
    }
}
