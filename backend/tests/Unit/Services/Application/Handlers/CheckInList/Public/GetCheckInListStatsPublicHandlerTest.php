<?php

namespace Tests\Unit\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Repository\DTO\CheckedInAttendeesCountDTO;
use HiEvents\Repository\DTO\CheckInListProductStatDTO;
use HiEvents\Repository\DTO\CheckInListRecentCheckInDTO;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListStatsPublicHandler;
use Mockery as m;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class GetCheckInListStatsPublicHandlerTest extends TestCase
{
    private CheckInListRepositoryInterface $checkInListRepository;
    private GetCheckInListStatsPublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkInListRepository = m::mock(CheckInListRepositoryInterface::class);

        $this->handler = new GetCheckInListStatsPublicHandler(
            $this->checkInListRepository
        );
    }

    public function testHandleThrowsNotFoundIfCheckInListMissing(): void
    {
        $this->checkInListRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['short_id' => 'short-id'])
            ->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle('short-id');
    }

    public function testHandleReturnsStatsDTO(): void
    {
        $checkInList = m::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('getId')->andReturn(42);
        $checkInList->shouldReceive('getEventOccurrenceId')->andReturn(null);

        $this->checkInListRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['short_id' => 'short-id'])
            ->andReturn($checkInList);

        $this->checkInListRepository
            ->shouldReceive('getCheckedInAttendeeCountById')
            ->once()
            ->with(42, null)
            ->andReturn(new CheckedInAttendeesCountDTO(
                checkInListId: 42,
                checkedInCount: 5,
                totalAttendeesCount: 20,
            ));

        $productStats = collect([
            new CheckInListProductStatDTO(
                productId: 1,
                productTitle: 'VIP',
                totalAttendees: 10,
                checkedInAttendees: 3,
            ),
            new CheckInListProductStatDTO(
                productId: 2,
                productTitle: 'General',
                totalAttendees: 10,
                checkedInAttendees: 2,
            ),
        ]);

        $this->checkInListRepository
            ->shouldReceive('getPerProductCheckInStatsById')
            ->once()
            ->with(42, null)
            ->andReturn($productStats);

        $recentCheckIns = collect([
            new CheckInListRecentCheckInDTO(
                attendeePublicId: 'A-AAAAAAAA',
                firstName: 'Alice',
                lastName: 'Smith',
                productTitle: 'VIP',
                checkedInAt: '2026-04-20T10:00:00Z',
            ),
        ]);

        $this->checkInListRepository
            ->shouldReceive('getRecentCheckInsById')
            ->once()
            ->with(42, 20, null)
            ->andReturn($recentCheckIns);

        $stats = $this->handler->handle('short-id');

        $this->assertSame(20, $stats->totalAttendees);
        $this->assertSame(5, $stats->checkedInAttendees);
        $this->assertCount(2, $stats->perProduct);
        $this->assertSame('VIP', $stats->perProduct[0]->productTitle);
        $this->assertSame(3, $stats->perProduct[0]->checkedInAttendees);
        $this->assertCount(1, $stats->recentCheckIns);
        $this->assertSame('Alice', $stats->recentCheckIns[0]->firstName);
    }

    public function testScopedListIgnoresClientOccurrenceFilter(): void
    {
        // A check-in list scoped to occurrence 99 must always report its own scope,
        // regardless of what the client passes. Passing null to the repository lets
        // it auto-scope via cil.event_occurrence_id.
        $checkInList = m::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('getId')->andReturn(42);
        $checkInList->shouldReceive('getEventOccurrenceId')->andReturn(99);

        $this->checkInListRepository
            ->shouldReceive('findFirstWhere')->once()
            ->andReturn($checkInList);

        $this->checkInListRepository->shouldReceive('getCheckedInAttendeeCountById')
            ->once()->with(42, null)
            ->andReturn(new CheckedInAttendeesCountDTO(checkInListId: 42, checkedInCount: 0, totalAttendeesCount: 0));

        $this->checkInListRepository->shouldReceive('getPerProductCheckInStatsById')
            ->once()->with(42, null)
            ->andReturn(collect());

        $this->checkInListRepository->shouldReceive('getRecentCheckInsById')
            ->once()->with(42, 20, null)
            ->andReturn(collect());

        // Client tries to override with occurrence 77; handler should ignore it.
        $this->handler->handle('short-id', 77);

        $this->assertTrue(true);
    }

    public function testUnscopedListRespectsClientOccurrenceFilter(): void
    {
        // Unscoped list ("All occurrences") — the client's filter-pill selection
        // propagates through to the repository so stats reflect the filtered view.
        $checkInList = m::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('getId')->andReturn(42);
        $checkInList->shouldReceive('getEventOccurrenceId')->andReturn(null);

        $this->checkInListRepository
            ->shouldReceive('findFirstWhere')->once()
            ->andReturn($checkInList);

        $this->checkInListRepository->shouldReceive('getCheckedInAttendeeCountById')
            ->once()->with(42, 77)
            ->andReturn(new CheckedInAttendeesCountDTO(checkInListId: 42, checkedInCount: 0, totalAttendeesCount: 0));

        $this->checkInListRepository->shouldReceive('getPerProductCheckInStatsById')
            ->once()->with(42, 77)
            ->andReturn(collect());

        $this->checkInListRepository->shouldReceive('getRecentCheckInsById')
            ->once()->with(42, 20, 77)
            ->andReturn(collect());

        $this->handler->handle('short-id', 77);

        $this->assertTrue(true);
    }
}
