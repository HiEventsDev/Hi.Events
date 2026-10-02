<?php

namespace Tests\Unit\Services\Infrastructure\Lock;

use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class TransactionLockServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_event_lock_uses_the_event_keyspace(): void
    {
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager
            ->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?, ?)', [TransactionLockService::EVENT_LOCK_KEYSPACE, 42]);

        (new TransactionLockService($databaseManager))->lockEvent(42);
    }

    public function test_order_lock_hashes_the_short_id_in_the_order_keyspace(): void
    {
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager
            ->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?, hashtext(?))', [TransactionLockService::ORDER_LOCK_KEYSPACE, 'o_abc123']);

        (new TransactionLockService($databaseManager))->lockOrder('o_abc123');
    }

    public function test_event_lock_key_stays_within_int4_range_for_bigint_ids(): void
    {
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager
            ->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?, ?)', [TransactionLockService::EVENT_LOCK_KEYSPACE, 5]);

        (new TransactionLockService($databaseManager))->lockEvent(TransactionLockService::MAX_LOCK_KEY + 5);
    }
}
