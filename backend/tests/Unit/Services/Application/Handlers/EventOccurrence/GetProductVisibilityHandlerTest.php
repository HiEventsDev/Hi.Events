<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductOccurrenceVisibilityDomainObjectAbstract;
use HiEvents\DomainObjects\ProductOccurrenceVisibilityDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetProductVisibilityHandler;
use Mockery;
use Tests\TestCase;

class GetProductVisibilityHandlerTest extends TestCase
{
    private ProductOccurrenceVisibilityRepositoryInterface|Mockery\MockInterface $visibilityRepository;
    private EventOccurrenceRepositoryInterface|Mockery\MockInterface $occurrenceRepository;
    private GetProductVisibilityHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visibilityRepository = Mockery::mock(ProductOccurrenceVisibilityRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->handler = new GetProductVisibilityHandler($this->visibilityRepository, $this->occurrenceRepository);
    }

    public function testHandleReturnsVisibilityRecords(): void
    {
        $occurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => 10,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => 1,
            ])
            ->andReturn($occurrence);

        $records = collect([Mockery::mock(ProductOccurrenceVisibilityDomainObject::class)]);
        $this->visibilityRepository->shouldReceive('findWhere')
            ->once()
            ->with([ProductOccurrenceVisibilityDomainObjectAbstract::EVENT_OCCURRENCE_ID => 10])
            ->andReturn($records);

        $result = $this->handler->handle(1, 10);

        $this->assertCount(1, $result);
    }

    public function testHandleThrowsWhenOccurrenceNotFound(): void
    {
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(1, 999);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
