<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\UpdateOrganizerLocationDTO;
use HiEvents\Services\Application\Handlers\Organizer\UpdateOrganizerLocationHandler;
use HiEvents\Services\Domain\Location\LocationLockService;
use HiEvents\Services\Domain\Location\LocationOwnershipValidator;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateOrganizerLocationHandlerTest extends TestCase
{
    private OrganizerRepositoryInterface|MockInterface $organizerRepository;

    private LocationRepositoryInterface|MockInterface $locationRepository;

    private LocationLockService|MockInterface $locationLockService;

    private UpdateOrganizerLocationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $this->locationRepository = Mockery::mock(LocationRepositoryInterface::class);
        $this->locationLockService = Mockery::mock(LocationLockService::class);
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new UpdateOrganizerLocationHandler(
            $this->organizerRepository,
            new LocationOwnershipValidator($this->locationRepository, $this->locationLockService),
            $databaseManager,
        );
    }

    public function test_happy_path_sets_location_id(): void
    {
        $dto = new UpdateOrganizerLocationDTO(organizer_id: 10, account_id: 5, location_id: 99);
        $organizer = Mockery::mock(OrganizerDomainObject::class);

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 10, 'account_id' => 5])
            ->andReturn($organizer, $organizer);

        $this->locationLockService
            ->shouldReceive('acquireSharedTransactionLock')
            ->once()
            ->with(99);

        $this->locationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 99, 'account_id' => 5, 'organizer_id' => 10])
            ->andReturn(Mockery::mock(LocationDomainObject::class));

        $this->organizerRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                ['location_id' => 99],
                ['id' => 10, 'account_id' => 5],
            );

        $this->assertSame($organizer, $this->handler->handle($dto));
    }

    public function test_null_location_id_clears_the_relation_without_validator_lookup(): void
    {
        $dto = new UpdateOrganizerLocationDTO(organizer_id: 10, account_id: 5, location_id: null);
        $organizer = Mockery::mock(OrganizerDomainObject::class);

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($organizer);

        $this->locationLockService->shouldNotReceive('acquireSharedTransactionLock');
        $this->locationRepository->shouldNotReceive('findFirstWhere');

        $this->organizerRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(['location_id' => null], ['id' => 10, 'account_id' => 5]);

        $this->assertSame($organizer, $this->handler->handle($dto));
    }

    public function test_throws_when_organizer_not_found(): void
    {
        $dto = new UpdateOrganizerLocationDTO(organizer_id: 10, account_id: 5, location_id: null);

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->handler->handle($dto);
    }

    public function test_throws_when_location_belongs_to_a_different_organizer_in_same_account(): void
    {
        $dto = new UpdateOrganizerLocationDTO(organizer_id: 10, account_id: 5, location_id: 99);

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(Mockery::mock(OrganizerDomainObject::class));

        $this->locationLockService
            ->shouldReceive('acquireSharedTransactionLock')
            ->once()
            ->with(99);

        $this->locationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 99, 'account_id' => 5, 'organizer_id' => 10])
            ->andReturn(null);

        $this->organizerRepository->shouldNotReceive('updateWhere');

        $this->expectException(ResourceNotFoundException::class);
        $this->handler->handle($dto);
    }

    public function test_throws_when_location_belongs_to_a_different_account(): void
    {
        $dto = new UpdateOrganizerLocationDTO(organizer_id: 10, account_id: 5, location_id: 99);

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(Mockery::mock(OrganizerDomainObject::class));

        $this->locationLockService
            ->shouldReceive('acquireSharedTransactionLock')
            ->once()
            ->with(99);

        $this->locationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->handler->handle($dto);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
