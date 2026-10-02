<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatingEventLockService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Mockery;
use Tests\TestCase;

class SeatingEventLockServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_the_seating_memos_are_dropped_only_after_the_event_lock_is_held(): void
    {
        $transactionLock = Mockery::mock(TransactionLockService::class);
        $seatMapLookup = Mockery::mock(EventSeatMapLookupService::class);
        $seatedProductLookup = Mockery::mock(SeatedProductLookupService::class);

        $transactionLock->shouldReceive('lockEvent')->once()->with(7)->ordered();
        $seatMapLookup->shouldReceive('forget')->once()->with(7)->ordered();
        $seatedProductLookup->shouldReceive('forget')->once()->withNoArgs()->ordered();

        (new SeatingEventLockService($transactionLock, $seatMapLookup, $seatedProductLookup))->lock(7);
    }
}
