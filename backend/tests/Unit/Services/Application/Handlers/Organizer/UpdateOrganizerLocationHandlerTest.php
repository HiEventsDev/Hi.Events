<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\UpdateOrganizerLocationDTO;
use HiEvents\Services\Application\Handlers\Organizer\UpdateOrganizerLocationHandler;
use HiEvents\Services\Domain\Location\LocationOwnershipValidator;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateOrganizerLocationHandlerTest extends TestCase
{
    private OrganizerRepositoryInterface|MockInterface $organizerRepository;

    private LocationRepositoryInterface|MockInterface $locationRepository;

    private UpdateOrganizerLocationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $this->locationRepository = Mockery::mock(LocationRepositoryInterface::class);
        $this->handler = new UpdateOrganizerLocationHandler(
            $this->organizerRepository,
            new LocationOwnershipValidator($this->locationRepository),
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

        // No location lookup should happen when clearing.
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
        // The IDOR vector this fix was written for: caller supplies a location_id
        // they don't own. The exists:locations,id rule on the request would let
        // it through; the validator must reject it.
        $dto = new UpdateOrganizerLocationDTO(organizer_id: 10, account_id: 5, location_id: 99);

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(Mockery::mock(OrganizerDomainObject::class));

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
        // Cross-tenant attack: the location with this id exists, but on
        // another account. The validator filter on account_id + organizer_id
        // is what stops it.
        $dto = new UpdateOrganizerLocationDTO(organizer_id: 10, account_id: 5, location_id: 99);

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(Mockery::mock(OrganizerDomainObject::class));

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
