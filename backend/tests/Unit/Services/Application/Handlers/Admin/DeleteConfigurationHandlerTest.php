<?php

namespace Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DeleteConfigurationHandler;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class DeleteConfigurationHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OrganizerConfigurationRepositoryInterface $repository;
    private OrganizerRepositoryInterface $organizerRepository;
    private DatabaseManager $databaseManager;
    private DeleteConfigurationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(OrganizerConfigurationRepositoryInterface::class);
        $this->organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->handler = new DeleteConfigurationHandler(
            repository: $this->repository,
            organizerRepository: $this->organizerRepository,
            databaseManager: $this->databaseManager,
        );
    }

    public function test_blocks_deleting_the_system_default(): void
    {
        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->andReturn((new OrganizerConfigurationDomainObject())->setIsSystemDefault(true));

        $this->expectException(CannotDeleteEntityException::class);

        $this->handler->handle(1);
    }

    public function test_blocks_deleting_a_configuration_with_assigned_organizers(): void
    {
        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->andReturn((new OrganizerConfigurationDomainObject())->setIsSystemDefault(false));

        $this->organizerRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['organizer_configuration_id' => 3])
            ->andReturn(2);

        $this->expectException(CannotDeleteEntityException::class);

        $this->handler->handle(3);
    }

    public function test_clears_inbound_upgrade_pointers_before_deleting(): void
    {
        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->andReturn((new OrganizerConfigurationDomainObject())->setIsSystemDefault(false));

        $this->organizerRepository
            ->shouldReceive('countWhere')
            ->once()
            ->andReturn(0);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->repository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(['upgrades_to_id' => null], ['upgrades_to_id' => 3]);

        $this->repository
            ->shouldReceive('deleteById')
            ->once()
            ->with(3);

        $this->handler->handle(3);
    }
}
