<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Enterprise\Seating\Services\Domain\AttendeeSeatMoveService;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\Badge\BadgeReprintService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Tests\TestCase;

class AttendeeSeatMoveServiceTest extends TestCase
{
    public function test_moving_an_attendee_flags_their_printed_badge_for_reprint(): void
    {
        $before = (new AttendeeDomainObject)->setId(7)->setEventId(3)->setSeatUid('seat-a')->setSeatLabel('Row A · 1');
        $after = (new AttendeeDomainObject)->setId(7)->setEventId(3)->setSeatUid('seat-b')->setSeatLabel('Row B · 4');
        $flagged = (new AttendeeDomainObject)->setId(7)->setEventId(3)->setSeatUid('seat-b')->setSeatLabel('Row B · 4');

        $seatClaimService = Mockery::mock(SeatClaimService::class);
        $seatClaimService->shouldReceive('move')->once();

        $attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $attendeeRepository->shouldReceive('findById')->with(7)->andReturn($after);

        $lookup = Mockery::mock(EventSeatMapLookupService::class);
        $lookup->shouldReceive('bandOf')->andReturn('stalls');

        $auditLog = Mockery::mock(OrderAuditLogService::class);
        $auditLog->shouldReceive('logAttendeeUpdate')->once();

        $dispatcher = Mockery::mock(DomainEventDispatcherService::class);
        $dispatcher->shouldReceive('dispatch')->once();

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $reprintService = Mockery::mock(BadgeReprintService::class);
        $reprintService->shouldReceive('flagIfBadgeDetailsChanged')->once()->with($before, $after)->andReturn($flagged);

        $service = new AttendeeSeatMoveService(
            $seatClaimService,
            $attendeeRepository,
            $auditLog,
            $dispatcher,
            $lookup,
            $databaseManager,
            $reprintService,
        );

        $this->assertSame($flagged, $service->move($before, 'seat-b', false, '127.0.0.1', null));
    }
}
