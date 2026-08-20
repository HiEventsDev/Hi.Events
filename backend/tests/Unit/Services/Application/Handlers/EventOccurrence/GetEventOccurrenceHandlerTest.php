<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetEventOccurrenceHandler;
use Mockery;
use Tests\TestCase;

class GetEventOccurrenceHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|Mockery\MockInterface $occurrenceRepository;

    private GetEventOccurrenceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->handler = new GetEventOccurrenceHandler($this->occurrenceRepository);
    }

    public function test_handle_returns_occurrence_with_stats(): void
    {
        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => 10,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => 1,
            ])
            ->andReturn($occurrence);

        $result = $this->handler->handle(1, 10);

        $this->assertSame($occurrence, $result);
    }

    public function test_handle_throws_when_occurrence_not_found(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(1, 999);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
