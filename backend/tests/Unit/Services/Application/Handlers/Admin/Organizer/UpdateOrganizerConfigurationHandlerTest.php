<?php

namespace Tests\Unit\Services\Application\Handlers\Admin\Organizer;

use HiEvents\DataTransferObjects\UpdateOrganizerConfigurationDTO;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\Organizer\UpdateOrganizerConfigurationHandler;
use Mockery as m;
use Tests\TestCase;

class UpdateOrganizerConfigurationHandlerTest extends TestCase
{
    private const APPLICATION_FEES = ['percentage' => 2.5, 'fixed' => 1.0, 'currency' => 'USD'];

    private UpdateOrganizerConfigurationHandler $handler;

    private OrganizerConfigurationRepositoryInterface $configurationRepository;

    private OrganizerRepositoryInterface $organizerRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurationRepository = m::mock(OrganizerConfigurationRepositoryInterface::class);
        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);

        $this->handler = new UpdateOrganizerConfigurationHandler(
            $this->configurationRepository,
            $this->organizerRepository,
        );
    }

    public function test_it_updates_a_dedicated_custom_configuration_in_place(): void
    {
        $this->givenOrganizerWithConfiguration(isSystemDefault: false, defaultForCurrency: null);

        $this->organizerRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['organizer_configuration_id' => 5])
            ->andReturn(1);

        $this->organizerRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['organizer_configuration_id' => 5, 'id' => 1])
            ->andReturn(1);

        $updated = (new OrganizerConfigurationDomainObject)->setId(5);

        $this->configurationRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(5, ['application_fees' => self::APPLICATION_FEES])
            ->andReturn($updated);

        $result = $this->handler->handle(new UpdateOrganizerConfigurationDTO(
            organizerId: 1,
            applicationFees: self::APPLICATION_FEES,
        ));

        $this->assertSame(5, $result->getId());
    }

    public function test_it_clones_instead_of_mutating_a_currency_default_configuration(): void
    {
        $this->givenOrganizerWithConfiguration(isSystemDefault: false, defaultForCurrency: 'AUD');

        $this->expectClone();

        $this->handler->handle(new UpdateOrganizerConfigurationDTO(
            organizerId: 1,
            applicationFees: self::APPLICATION_FEES,
        ));

        $this->addToAssertionCount(1);
    }

    public function test_it_clones_instead_of_mutating_the_system_default_configuration(): void
    {
        $this->givenOrganizerWithConfiguration(isSystemDefault: true, defaultForCurrency: null);

        $this->expectClone();

        $this->handler->handle(new UpdateOrganizerConfigurationDTO(
            organizerId: 1,
            applicationFees: self::APPLICATION_FEES,
        ));

        $this->addToAssertionCount(1);
    }

    private function givenOrganizerWithConfiguration(bool $isSystemDefault, ?string $defaultForCurrency): void
    {
        $configuration = (new OrganizerConfigurationDomainObject)
            ->setId(5)
            ->setIsSystemDefault($isSystemDefault)
            ->setDefaultForCurrency($defaultForCurrency);

        $organizer = (new OrganizerDomainObject)
            ->setId(1)
            ->setName('Acme Events');
        $organizer->setOrganizerConfiguration($configuration);

        $this->organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->organizerRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($organizer);
    }

    private function expectClone(): void
    {
        $clone = (new OrganizerConfigurationDomainObject)->setId(6);

        $this->configurationRepository
            ->shouldReceive('create')
            ->once()
            ->with([
                'name' => 'Acme Events (#1) - Custom Fees',
                'is_system_default' => false,
                'application_fees' => self::APPLICATION_FEES,
            ])
            ->andReturn($clone);

        $this->organizerRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, ['organizer_configuration_id' => 6])
            ->andReturn(m::mock(OrganizerDomainObject::class));
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
