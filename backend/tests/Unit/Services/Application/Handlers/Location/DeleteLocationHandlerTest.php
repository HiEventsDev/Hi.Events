<?php

namespace Tests\Unit\Services\Application\Handlers\Location;

use HiEvents\DomainObjects\Generated\LocationDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use HiEvents\Services\Application\Handlers\Location\DeleteLocationHandler;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class DeleteLocationHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private LocationRepositoryInterface|MockInterface $locationRepository;

    private DeleteLocationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->locationRepository = Mockery::mock(LocationRepositoryInterface::class);
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new DeleteLocationHandler($this->locationRepository, $databaseManager);
    }

    public function test_deletes_unreferenced_location(): void
    {
        $this->expectLocationLookup(Mockery::mock(LocationDomainObject::class));
        $this->locationRepository->shouldReceive('isReferenced')->with(3)->andReturn(false);
        $this->locationRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([LocationDomainObjectAbstract::ID => 3]);

        $this->handler->handle(10, 5, 3);
    }

    public function test_throws_when_location_not_found(): void
    {
        $this->expectLocationLookup(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->handler->handle(10, 5, 3);
    }

    public function test_throws_conflict_when_location_is_referenced(): void
    {
        $this->expectLocationLookup(Mockery::mock(LocationDomainObject::class));
        $this->locationRepository->shouldReceive('isReferenced')->with(3)->andReturn(true);
        $this->locationRepository->shouldNotReceive('deleteWhere');

        $this->expectException(ResourceConflictException::class);
        $this->handler->handle(10, 5, 3);
    }

    private function expectLocationLookup(?LocationDomainObject $location): void
    {
        $this->locationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                LocationDomainObjectAbstract::ID => 3,
                LocationDomainObjectAbstract::ORGANIZER_ID => 10,
                LocationDomainObjectAbstract::ACCOUNT_ID => 5,
            ])
            ->andReturn($location);
    }
}
