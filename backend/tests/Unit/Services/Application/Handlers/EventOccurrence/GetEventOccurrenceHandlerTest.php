<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventOccurrenceStatisticDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetEventOccurrenceHandler;
use Mockery;
use HiEvents\Exceptions\ResourceNotFoundException;
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

    public function testHandleReturnsOccurrenceWithStats(): void
    {
        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('loadRelation')
            ->with(EventOccurrenceStatisticDomainObject::class)
            ->once()
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

    public function testHandleThrowsWhenOccurrenceNotFound(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('loadRelation')
            ->once()
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
