<?php

namespace Tests\Unit\Services\Domain\Organizer;

use HiEvents\DomainObjects\Generated\OrganizerConfigurationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrganizerDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Organizer\AssignCurrencyDefaultOrganizerConfigurationService;
use Illuminate\Config\Repository;
use Mockery as m;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class AssignCurrencyDefaultOrganizerConfigurationServiceTest extends TestCase
{
    private AssignCurrencyDefaultOrganizerConfigurationService $service;

    private OrganizerRepositoryInterface $organizerRepository;

    private OrganizerConfigurationRepositoryInterface $organizerConfigurationRepository;

    private Repository $config;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->organizerConfigurationRepository = m::mock(OrganizerConfigurationRepositoryInterface::class);
        $this->config = m::mock(Repository::class);
        $this->logger = m::mock(LoggerInterface::class);

        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn(true)->byDefault();
        $this->logger->shouldReceive('info')->byDefault();
        $this->organizerConfigurationRepository->shouldReceive('countWhere')->andReturn(4)->byDefault();

        $this->service = new AssignCurrencyDefaultOrganizerConfigurationService(
            $this->organizerRepository,
            $this->organizerConfigurationRepository,
            $this->config,
            $this->logger,
        );
    }

    public function test_it_does_nothing_when_saas_mode_is_disabled(): void
    {
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn(false);

        $this->service->assignForCountry(1, 'US');

        $this->addToAssertionCount(1);
    }

    public function test_it_does_nothing_when_country_is_missing(): void
    {
        $this->service->assignForCountry(1, null);
        $this->service->assignForCountry(1, '  ');

        $this->addToAssertionCount(1);
    }

    public function test_it_stays_silent_when_no_currency_default_configurations_exist(): void
    {
        $this->organizerConfigurationRepository->shouldReceive('countWhere')->once()->andReturn(0);

        $this->service->assignForCountry(1, 'US');

        $this->addToAssertionCount(1);
    }

    #[DataProvider('countryToCurrencyProvider')]
    public function test_it_maps_country_to_currency_default_configuration(string $country, string $expectedCurrency): void
    {
        $this->givenOrganizer(1, currentConfigurationId: null);
        $this->givenCurrencyDefaultConfiguration($expectedCurrency, configurationId: 99);
        $this->expectAssignment(organizerId: 1, configurationId: 99);

        $this->service->assignForCountry(1, $country);
    }

    public static function countryToCurrencyProvider(): array
    {
        return [
            'US maps to USD' => ['US', 'USD'],
            'GB maps to GBP' => ['GB', 'GBP'],
            'AU maps to AUD' => ['AU', 'AUD'],
            'lowercase us is normalized' => ['us', 'USD'],
            'DE falls back to EUR' => ['DE', 'EUR'],
            'BR falls back to EUR' => ['BR', 'EUR'],
            'unknown code falls back to EUR' => ['XX', 'EUR'],
        ];
    }

    public function test_it_reassigns_organizer_on_system_default_configuration(): void
    {
        $this->givenOrganizer(1, currentConfigurationId: 5);
        $this->givenCurrentConfiguration(5, isSystemDefault: true, defaultForCurrency: null);
        $this->givenCurrencyDefaultConfiguration('USD', configurationId: 99);
        $this->expectAssignment(organizerId: 1, configurationId: 99);

        $this->service->assignForCountry(1, 'US');
    }

    public function test_it_remaps_organizer_on_another_currency_default_configuration(): void
    {
        $this->givenOrganizer(1, currentConfigurationId: 7);
        $this->givenCurrentConfiguration(7, isSystemDefault: false, defaultForCurrency: 'USD');
        $this->givenCurrencyDefaultConfiguration('EUR', configurationId: 88);
        $this->expectAssignment(organizerId: 1, configurationId: 88);

        $this->service->assignForCountry(1, 'DE');
    }

    public function test_it_does_not_touch_organizer_on_custom_configuration(): void
    {
        $this->givenOrganizer(1, currentConfigurationId: 12);
        $this->givenCurrentConfiguration(12, isSystemDefault: false, defaultForCurrency: null);

        $this->service->assignForCountry(1, 'US');

        $this->addToAssertionCount(1);
    }

    public function test_it_assigns_when_current_configuration_is_soft_deleted(): void
    {
        $this->givenOrganizer(1, currentConfigurationId: 12);
        $this->organizerConfigurationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 12])
            ->andReturnNull();
        $this->givenCurrencyDefaultConfiguration('USD', configurationId: 99);
        $this->expectAssignment(organizerId: 1, configurationId: 99);

        $this->service->assignForCountry(1, 'US');
    }

    public function test_it_short_circuits_when_already_on_target_configuration(): void
    {
        $this->givenOrganizer(1, currentConfigurationId: 99);
        $this->givenCurrentConfiguration(99, isSystemDefault: false, defaultForCurrency: 'USD');
        $this->givenCurrencyDefaultConfiguration('USD', configurationId: 99);

        $this->service->assignForCountry(1, 'US');

        $this->addToAssertionCount(1);
    }

    public function test_it_logs_warning_when_the_currency_default_configuration_is_missing(): void
    {
        $this->givenOrganizer(1, currentConfigurationId: null);
        $this->organizerConfigurationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([OrganizerConfigurationDomainObjectAbstract::DEFAULT_FOR_CURRENCY => 'USD'])
            ->andReturnNull();

        $this->logger->shouldReceive('warning')->once();

        $this->service->assignForCountry(1, 'US');

        $this->addToAssertionCount(1);
    }

    public function test_it_does_nothing_when_organizer_is_not_found(): void
    {
        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 1])
            ->andReturnNull();

        $this->service->assignForCountry(1, 'US');

        $this->addToAssertionCount(1);
    }

    public function test_it_catches_and_logs_repository_exceptions(): void
    {
        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andThrow(new RuntimeException('db down'));

        $this->logger->shouldReceive('error')->once();

        $this->service->assignForCountry(1, 'US');

        $this->addToAssertionCount(1);
    }

    private function givenOrganizer(int $organizerId, ?int $currentConfigurationId): void
    {
        $organizer = (new OrganizerDomainObject)->setId($organizerId);

        if ($currentConfigurationId !== null) {
            $organizer->setOrganizerConfigurationId($currentConfigurationId);
        }

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => $organizerId])
            ->andReturn($organizer);
    }

    private function givenCurrentConfiguration(int $configurationId, bool $isSystemDefault, ?string $defaultForCurrency): void
    {
        $configuration = (new OrganizerConfigurationDomainObject)
            ->setId($configurationId)
            ->setIsSystemDefault($isSystemDefault)
            ->setDefaultForCurrency($defaultForCurrency);

        $this->organizerConfigurationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => $configurationId])
            ->andReturn($configuration);
    }

    private function givenCurrencyDefaultConfiguration(string $currency, int $configurationId): void
    {
        $configuration = (new OrganizerConfigurationDomainObject)
            ->setId($configurationId)
            ->setIsSystemDefault(false)
            ->setDefaultForCurrency($currency);

        $this->organizerConfigurationRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([OrganizerConfigurationDomainObjectAbstract::DEFAULT_FOR_CURRENCY => $currency])
            ->andReturn($configuration);
    }

    private function expectAssignment(int $organizerId, int $configurationId): void
    {
        $this->organizerRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [OrganizerDomainObjectAbstract::ORGANIZER_CONFIGURATION_ID => $configurationId],
                ['id' => $organizerId],
            )
            ->andReturn(1);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
