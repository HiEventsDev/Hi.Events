<?php

namespace Tests\Unit\Services\Domain\Location;

use HiEvents\Services\Domain\Location\LocationLockService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class LocationLockServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_shared_lock_uses_shared_advisory_lock_in_locations_keyspace(): void
    {
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager
            ->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock_shared(?, ?)', [LocationLockService::LOCATIONS_LOCK_KEYSPACE, 42]);

        (new LocationLockService($databaseManager))->acquireSharedTransactionLock(42);
    }

    public function test_exclusive_lock_uses_exclusive_advisory_lock_in_locations_keyspace(): void
    {
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager
            ->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?, ?)', [LocationLockService::LOCATIONS_LOCK_KEYSPACE, 42]);

        (new LocationLockService($databaseManager))->acquireExclusiveTransactionLock(42);
    }

    public function test_lock_key_stays_within_int4_range_for_bigint_ids(): void
    {
        $bigintId = 2147483647 + 5;

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager
            ->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?, ?)', [LocationLockService::LOCATIONS_LOCK_KEYSPACE, 5]);
        $databaseManager
            ->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock_shared(?, ?)', [LocationLockService::LOCATIONS_LOCK_KEYSPACE, 5]);

        $service = new LocationLockService($databaseManager);
        $service->acquireExclusiveTransactionLock($bigintId);
        $service->acquireSharedTransactionLock($bigintId);
    }
}
