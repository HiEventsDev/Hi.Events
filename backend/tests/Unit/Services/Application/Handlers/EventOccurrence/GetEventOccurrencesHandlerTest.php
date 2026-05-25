<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetEventOccurrencesHandler;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class GetEventOccurrencesHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|Mockery\MockInterface $occurrenceRepository;

    private GetEventOccurrencesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->handler = new GetEventOccurrencesHandler($this->occurrenceRepository);
    }

    public function test_handle_returns_paginated_occurrences_with_stats(): void
    {
        $queryParams = Mockery::mock(QueryParamsDTO::class);
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->occurrenceRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->occurrenceRepository
            ->shouldReceive('findByEventId')
            ->once()
            ->with(1, $queryParams)
            ->andReturn($paginator);

        $result = $this->handler->handle(1, $queryParams);

        $this->assertSame($paginator, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
