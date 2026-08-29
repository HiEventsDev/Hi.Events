<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Organizer;

use HiEvents\DomainObjects\Generated\OrganizerConfigurationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrganizerDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Config\Repository;
use Psr\Log\LoggerInterface;
use Throwable;

class AssignCurrencyDefaultOrganizerConfigurationService
{
    private const array CURRENCY_BY_COUNTRY = [
        'US' => 'USD',
        'GB' => 'GBP',
        'AU' => 'AUD',
    ];

    private const string FALLBACK_CURRENCY = 'EUR';

    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly OrganizerConfigurationRepositoryInterface $organizerConfigurationRepository,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function assignForCountry(int $organizerId, ?string $countryCode): void
    {
        if (! $this->config->get('app.saas_mode_enabled')) {
            return;
        }

        try {
            $this->assign($organizerId, $countryCode);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to assign currency default organizer configuration', [
                'organizer_id' => $organizerId,
                'country_code' => $countryCode,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function assign(int $organizerId, ?string $countryCode): void
    {
        if ($countryCode === null || trim($countryCode) === '') {
            $this->logger->info('No Stripe account country available, skipping configuration assignment', [
                'organizer_id' => $organizerId,
            ]);

            return;
        }

        $currency = self::CURRENCY_BY_COUNTRY[strtoupper(trim($countryCode))] ?? self::FALLBACK_CURRENCY;

        if (! $this->currencyDefaultConfigurationsExist()) {
            return;
        }

        /** @var OrganizerDomainObject|null $organizer */
        $organizer = $this->organizerRepository->findFirstWhere(['id' => $organizerId]);
        if ($organizer === null) {
            return;
        }

        if (! $this->isOrganizerOnDefaultConfiguration($organizer)) {
            return;
        }

        /** @var OrganizerConfigurationDomainObject|null $target */
        $target = $this->organizerConfigurationRepository->findFirstWhere([
            OrganizerConfigurationDomainObjectAbstract::DEFAULT_FOR_CURRENCY => $currency,
        ]);

        if ($target === null) {
            $this->logger->warning('No default organizer configuration found for currency', [
                'organizer_id' => $organizerId,
                'country_code' => $countryCode,
                'currency' => $currency,
            ]);

            return;
        }

        if ($target->getId() === $organizer->getOrganizerConfigurationId()) {
            return;
        }

        $this->organizerRepository->updateWhere(
            attributes: [
                OrganizerDomainObjectAbstract::ORGANIZER_CONFIGURATION_ID => $target->getId(),
            ],
            where: [
                'id' => $organizerId,
            ],
        );

        $this->logger->info('Assigned currency default configuration to organizer', [
            'organizer_id' => $organizerId,
            'country_code' => $countryCode,
            'currency' => $currency,
            'organizer_configuration_id' => $target->getId(),
        ]);
    }

    private function currencyDefaultConfigurationsExist(): bool
    {
        return $this->organizerConfigurationRepository->countWhere([
            [OrganizerConfigurationDomainObjectAbstract::DEFAULT_FOR_CURRENCY, 'not null', null],
        ]) > 0;
    }

    private function isOrganizerOnDefaultConfiguration(OrganizerDomainObject $organizer): bool
    {
        if ($organizer->getOrganizerConfigurationId() === null) {
            return true;
        }

        /** @var OrganizerConfigurationDomainObject|null $currentConfiguration */
        $currentConfiguration = $this->organizerConfigurationRepository->findFirstWhere([
            'id' => $organizer->getOrganizerConfigurationId(),
        ]);

        if ($currentConfiguration === null) {
            return true;
        }

        return $currentConfiguration->isDefault();
    }
}
