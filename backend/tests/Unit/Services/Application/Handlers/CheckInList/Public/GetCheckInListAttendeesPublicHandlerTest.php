<?php

namespace Tests\Unit\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Http\DTO\FilterFieldDTO;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListAttendeesPublicHandler;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class GetCheckInListAttendeesPublicHandlerTest extends TestCase
{
    private CheckInListRepositoryInterface $checkInListRepository;
    private AttendeeRepositoryInterface $attendeeRepository;
    private GetCheckInListAttendeesPublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkInListRepository = m::mock(CheckInListRepositoryInterface::class);
        $this->attendeeRepository = m::mock(AttendeeRepositoryInterface::class);

        $this->handler = new GetCheckInListAttendeesPublicHandler(
            $this->attendeeRepository,
            $this->checkInListRepository,
        );
    }

    private function buildCheckInList(?int $occurrenceId): CheckInListDomainObject
    {
        $list = m::mock(CheckInListDomainObject::class);
        $list->shouldReceive('getId')->andReturn(1);
        $list->shouldReceive('getEventOccurrenceId')->andReturn($occurrenceId);
        $list->shouldReceive('getExpiresAt')->andReturn(null);
        $list->shouldReceive('getActivatesAt')->andReturn(null);

        return $list;
    }

    private function expectCheckInListLoaded(CheckInListDomainObject $list): void
    {
        $this->checkInListRepository
            ->shouldReceive('loadRelation')->andReturnSelf();
        $this->checkInListRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($list);
    }

    private function emptyAttendeePaginator(): Paginator
    {
        return new Paginator(collect([]), 10);
    }

    public function testInjectsCheckInListOccurrenceFilterWhenListIsScoped(): void
    {
        $this->expectCheckInListLoaded($this->buildCheckInList(occurrenceId: 42));

        $capturedParams = null;
        $this->attendeeRepository
            ->shouldReceive('getAttendeesByCheckInShortId')
            ->once()
            ->with('short-id', m::on(function (QueryParamsDTO $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            }))
            ->andReturn($this->emptyAttendeePaginator());

        $this->handler->handle('short-id', new QueryParamsDTO());

        $this->assertNotNull($capturedParams);
        $filter = $capturedParams->filter_fields->firstWhere('field', 'event_occurrence_id');
        $this->assertNotNull($filter, 'expected event_occurrence_id filter to be injected');
        $this->assertSame('42', $filter->value);
    }

    public function testOverridesClientSuppliedOccurrenceFilterForScopedList(): void
    {
        // A misbehaving or stale client might try to pass a different occurrence id.
        // For a scoped list we must ignore it and force the list's own occurrence.
        $this->expectCheckInListLoaded($this->buildCheckInList(occurrenceId: 42));

        $capturedParams = null;
        $this->attendeeRepository
            ->shouldReceive('getAttendeesByCheckInShortId')
            ->once()
            ->with('short-id', m::on(function (QueryParamsDTO $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            }))
            ->andReturn($this->emptyAttendeePaginator());

        $clientParams = new QueryParamsDTO(filter_fields: collect([
            new FilterFieldDTO(field: 'event_occurrence_id', operator: 'eq', value: '99'),
            new FilterFieldDTO(field: 'status', operator: 'eq', value: 'ACTIVE'),
        ]));

        $this->handler->handle('short-id', $clientParams);

        /** @var Collection $filters */
        $filters = $capturedParams->filter_fields;
        $occurrenceFilters = $filters->where('field', 'event_occurrence_id');
        $this->assertCount(1, $occurrenceFilters, 'client filter must be replaced, not appended');
        $this->assertSame('42', $occurrenceFilters->first()->value);

        // Non-occurrence client filters are preserved.
        $this->assertCount(1, $filters->where('field', 'status'));
    }

    public function testLeavesClientSuppliedOccurrenceFilterAloneWhenListIsNotScoped(): void
    {
        // Unscoped ("All occurrences") list — respect the client's optional filter
        // (this is how the filter pill in the check-in UI works).
        $this->expectCheckInListLoaded($this->buildCheckInList(occurrenceId: null));

        $capturedParams = null;
        $this->attendeeRepository
            ->shouldReceive('getAttendeesByCheckInShortId')
            ->once()
            ->with('short-id', m::on(function (QueryParamsDTO $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            }))
            ->andReturn($this->emptyAttendeePaginator());

        $clientParams = new QueryParamsDTO(filter_fields: collect([
            new FilterFieldDTO(field: 'event_occurrence_id', operator: 'eq', value: '77'),
        ]));

        $this->handler->handle('short-id', $clientParams);

        $filter = $capturedParams->filter_fields->firstWhere('field', 'event_occurrence_id');
        $this->assertNotNull($filter);
        $this->assertSame('77', $filter->value);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
