<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence\PriceOverride;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceOccurrenceOverrideDomainObjectAbstract;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\DeletePriceOverrideHandler;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeletePriceOverrideHandlerTest extends TestCase
{
    private ProductPriceOccurrenceOverrideRepositoryInterface|MockInterface $overrideRepository;
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;
    private DatabaseManager|MockInterface $databaseManager;
    private DeletePriceOverrideHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->overrideRepository = Mockery::mock(ProductPriceOccurrenceOverrideRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new DeletePriceOverrideHandler(
            $this->overrideRepository,
            $this->occurrenceRepository,
            $this->databaseManager,
        );
    }

    public function testHandleSuccessfullyDeletesOverrideScopedToOccurrence(): void
    {
        $eventId = 1;
        $occurrenceId = 10;
        $overrideId = 5;

        $existingOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $existingOverride = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn($existingOccurrence);

        $this->overrideRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::ID => $overrideId,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
            ])
            ->andReturn($existingOverride);

        $this->overrideRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::ID => $overrideId,
            ]);

        $this->handler->handle($eventId, $occurrenceId, $overrideId);

        $this->assertTrue(true);
    }

    public function testHandleThrowsExceptionWhenOccurrenceDoesNotBelongToEvent(): void
    {
        $eventId = 1;
        $occurrenceId = 10;
        $overrideId = 5;

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ])
            ->andReturn(null);

        $this->overrideRepository->shouldNotReceive('findFirstWhere');
        $this->overrideRepository->shouldNotReceive('deleteWhere');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($eventId, $occurrenceId, $overrideId);
    }

    public function testHandleThrowsExceptionWhenOverrideNotFound(): void
    {
        $eventId = 1;
        $occurrenceId = 10;
        $overrideId = 999;

        $existingOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($existingOccurrence);

        $this->overrideRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::ID => $overrideId,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
            ])
            ->andReturn(null);

        $this->overrideRepository->shouldNotReceive('deleteWhere');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($eventId, $occurrenceId, $overrideId);
    }

    public function testHandleScopesLookupToOccurrenceId(): void
    {
        $eventId = 1;
        $occurrenceId = 42;
        $overrideId = 7;

        $existingOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($existingOccurrence);

        $this->overrideRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(Mockery::on(function ($arg) use ($occurrenceId, $overrideId) {
                return $arg[ProductPriceOccurrenceOverrideDomainObjectAbstract::ID] === $overrideId
                    && $arg[ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID] === $occurrenceId;
            }))
            ->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($eventId, $occurrenceId, $overrideId);
    }

    public function testHandleDeletesOnlyTheSpecifiedOverride(): void
    {
        $eventId = 1;
        $occurrenceId = 10;
        $overrideId = 3;

        $existingOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);
        $existingOverride = Mockery::mock(ProductPriceOccurrenceOverrideDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($existingOccurrence);

        $this->overrideRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($existingOverride);

        $this->overrideRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::ID => $overrideId,
            ]);

        $this->handler->handle($eventId, $occurrenceId, $overrideId);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
