<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetEventOccurrencesHandler;
use HiEvents\Services\Domain\EventOccurrence\OccurrenceBookingLimitsService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class GetEventOccurrencesHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|Mockery\MockInterface $occurrenceRepository;

    private OccurrenceBookingLimitsService|Mockery\MockInterface $bookingLimitsService;

    private GetEventOccurrencesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->bookingLimitsService = Mockery::mock(OccurrenceBookingLimitsService::class);
        $this->handler = new GetEventOccurrencesHandler(
            $this->occurrenceRepository,
            $this->bookingLimitsService,
        );
    }

    public function test_handle_returns_paginated_occurrences_with_booking_limits_attached(): void
    {
        $queryParams = Mockery::mock(QueryParamsDTO::class);
        $occurrence = (new EventOccurrenceDomainObject)->setId(7);
        $paginator = new LengthAwarePaginator(collect([$occurrence]), 1, 50);
        $this->occurrenceRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->occurrenceRepository->shouldReceive('findByEventId')->once()->with(1, $queryParams)->andReturn($paginator);
        $this->bookingLimitsService->shouldReceive('attachTo')
            ->once()
            ->withArgs(fn ($occurrences, int $eventId) => $occurrences->first() === $occurrence && $eventId === 1);

        $result = $this->handler->handle(1, $queryParams);

        $this->assertSame($paginator, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
