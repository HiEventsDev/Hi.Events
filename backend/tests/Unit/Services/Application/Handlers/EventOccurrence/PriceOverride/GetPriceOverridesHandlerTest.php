<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence\PriceOverride;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\ProductPriceOccurrenceOverrideDomainObjectAbstract;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\GetPriceOverridesHandler;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetPriceOverridesHandlerTest extends TestCase
{
    private ProductPriceOccurrenceOverrideRepositoryInterface|MockInterface $overrideRepository;
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;
    private GetPriceOverridesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->overrideRepository = Mockery::mock(ProductPriceOccurrenceOverrideRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->handler = new GetPriceOverridesHandler($this->overrideRepository, $this->occurrenceRepository);
    }

    private function mockOccurrenceOwnership(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));
    }

    public function testHandleReturnsCollectionOfOverridesForOccurrence(): void
    {
        $this->mockOccurrenceOwnership();

        $override1 = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);
        $override2 = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);
        $expectedCollection = new Collection([$override1, $override2]);

        $this->overrideRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with([ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => 10])
            ->andReturn($expectedCollection);

        $result = $this->handler->handle(1, 10);

        $this->assertCount(2, $result);
        $this->assertSame($expectedCollection, $result);
    }

    public function testHandleReturnsEmptyCollectionWhenNoneExist(): void
    {
        $this->mockOccurrenceOwnership();

        $this->overrideRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection());

        $result = $this->handler->handle(1, 99);

        $this->assertTrue($result->isEmpty());
    }

    public function testHandleThrowsWhenOccurrenceDoesNotBelongToEvent(): void
    {
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(null);

        $this->expectException(\HiEvents\Exceptions\ResourceNotFoundException::class);

        $this->handler->handle(1, 999);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
