<?php

namespace Tests\Unit\Services\Domain\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Order\OrderReservationLapseGuard;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderReservationLapseGuardTest extends TestCase
{
    private TransactionLockService|MockInterface $lockService;

    private OrderReservationLapseGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockService = Mockery::mock(TransactionLockService::class);
        $this->guard = new OrderReservationLapseGuard($this->lockService);
    }

    public function test_a_reservation_with_time_to_spare_takes_no_event_lock(): void
    {
        $this->lockService->shouldNotReceive('lockEvent');

        $this->guard->assertReservationHeldUnderEventLock($this->order(Carbon::now()->addMinutes(10)));

        $this->assertTrue(true);
    }

    public function test_a_reservation_about_to_lapse_is_checked_under_the_event_lock(): void
    {
        $this->lockService->shouldReceive('lockEvent')->once()->with(7);

        $this->guard->assertReservationHeldUnderEventLock($this->order(Carbon::now()->addSeconds(30)));

        $this->assertTrue(true);
    }

    public function test_a_reservation_that_lapsed_while_waiting_for_the_lock_is_refused(): void
    {
        $this->lockService->shouldReceive('lockEvent')->once()->with(7);

        $this->expectException(ResourceConflictException::class);

        $this->guard->assertReservationHeldUnderEventLock($this->order(Carbon::now()->subSecond()));
    }

    private function order(Carbon $reservedUntil): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setEventId(7)
            ->setReservedUntil($reservedUntil->toDateTimeString());
    }
}
