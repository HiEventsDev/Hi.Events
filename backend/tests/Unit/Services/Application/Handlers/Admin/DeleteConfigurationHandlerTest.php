<?php

namespace Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DeleteConfigurationHandler;
use Mockery as m;
use Tests\TestCase;

class DeleteConfigurationHandlerTest extends TestCase
{
    private DeleteConfigurationHandler $handler;

    private OrganizerConfigurationRepositoryInterface $repository;

    private OrganizerRepositoryInterface $organizerRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = m::mock(OrganizerConfigurationRepositoryInterface::class);
        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);

        $this->handler = new DeleteConfigurationHandler(
            $this->repository,
            $this->organizerRepository,
        );
    }

    public function test_it_refuses_to_delete_the_system_default_configuration(): void
    {
        $this->givenConfiguration(isSystemDefault: true, defaultForCurrency: null);

        $this->expectException(CannotDeleteEntityException::class);

        $this->handler->handle(1);
    }

    public function test_it_refuses_to_delete_a_currency_default_configuration(): void
    {
        $this->givenConfiguration(isSystemDefault: false, defaultForCurrency: 'AUD');

        $this->expectException(CannotDeleteEntityException::class);

        $this->handler->handle(1);
    }

    public function test_it_refuses_to_delete_a_configuration_with_assigned_organizers(): void
    {
        $this->givenConfiguration(isSystemDefault: false, defaultForCurrency: null);
        $this->organizerRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['organizer_configuration_id' => 1])
            ->andReturn(3);

        $this->expectException(CannotDeleteEntityException::class);

        $this->handler->handle(1);
    }

    public function test_it_deletes_an_unassigned_custom_configuration(): void
    {
        $this->givenConfiguration(isSystemDefault: false, defaultForCurrency: null);
        $this->organizerRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['organizer_configuration_id' => 1])
            ->andReturn(0);

        $this->repository
            ->shouldReceive('deleteById')
            ->once()
            ->with(1);

        $this->handler->handle(1);

        $this->addToAssertionCount(1);
    }

    private function givenConfiguration(bool $isSystemDefault, ?string $defaultForCurrency): void
    {
        $configuration = (new OrganizerConfigurationDomainObject)
            ->setId(1)
            ->setIsSystemDefault($isSystemDefault)
            ->setDefaultForCurrency($defaultForCurrency);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($configuration);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
