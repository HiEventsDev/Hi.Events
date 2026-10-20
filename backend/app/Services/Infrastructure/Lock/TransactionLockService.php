<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Lock;

use Illuminate\Database\DatabaseManager;

class TransactionLockService
{
    public const EVENT_LOCK_KEYSPACE = 0x45564E;

    public const ORDER_LOCK_KEYSPACE = 0x4F5244;

    public const MAX_LOCK_KEY = 2147483647;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function lockEvent(int $eventId): void
    {
        $this->databaseManager->statement(
            'SELECT pg_advisory_xact_lock(?, ?)',
            [self::EVENT_LOCK_KEYSPACE, $eventId % self::MAX_LOCK_KEY],
        );
    }

    public function lockOrder(string $orderShortId): void
    {
        $this->databaseManager->statement(
            'SELECT pg_advisory_xact_lock(?, hashtext(?))',
            [self::ORDER_LOCK_KEYSPACE, $orderShortId],
        );
    }
}
