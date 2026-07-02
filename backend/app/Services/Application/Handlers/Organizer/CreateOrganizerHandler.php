<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\CreateOrganizerDTO;
use HiEvents\Services\Domain\Organizer\CreateDefaultOrganizerSettingsService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class CreateOrganizerHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface              $organizerRepository,
        private readonly OrganizerConfigurationRepositoryInterface $organizerConfigurationRepository,
        private readonly AccountRepositoryInterface                $accountRepository,
        private readonly DatabaseManager                           $databaseManager,
        private readonly CreateDefaultOrganizerSettingsService     $createDefaultOrganizerSettingsService,
        private readonly HtmlPurifierService                       $purifier,
        private readonly LoggerInterface                           $logger,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CreateOrganizerDTO $organizerData): OrganizerDomainObject
    {
        return $this->databaseManager->transaction(
            fn() => $this->createOrganizer($organizerData)
        );
    }

    private function createOrganizer(CreateOrganizerDTO $organizerData): OrganizerDomainObject
    {
        $organizer = $this->organizerRepository->create([
            'name' => $organizerData->name,
            'email' => $organizerData->email,
            'phone' => $organizerData->phone,
            'website' => $organizerData->website,
            'description' => $this->purifier->purify($organizerData->description),
            'account_id' => $organizerData->account_id,
            'timezone' => $organizerData->timezone,
            'currency' => $organizerData->currency,
            'organizer_configuration_id' => $this->resolveConfigurationId($organizerData->account_id),
        ]);

        $this->createDefaultOrganizerSettingsService->createOrganizerSettings($organizer);

        return $this->organizerRepository
            ->loadRelation(ImageDomainObject::class)
            ->findById($organizer->getId());
    }

    /**
     * Prefer the parent account's plan via the legacy id pointer kept on
     * organizer_configurations during the deprecation window — handles SaaS
     * invite tokens that pre-assign a custom account_configuration. Falls back
     * to the organizer-level system default.
     */
    private function resolveConfigurationId(int $accountId): ?int
    {
        $account = $this->accountRepository->findFirst($accountId);
        $legacyAccountConfigurationId = $account?->getAccountConfigurationId();

        if ($legacyAccountConfigurationId !== null) {
            $matched = $this->organizerConfigurationRepository->findFirstWhere([
                'legacy_account_configuration_id' => $legacyAccountConfigurationId,
            ]);

            if ($matched !== null) {
                return $matched->getId();
            }
        }

        $defaultConfiguration = $this->organizerConfigurationRepository->findFirstWhere([
            'is_system_default' => true,
        ]);

        if ($defaultConfiguration === null) {
            $this->logger->error('No default organizer configuration found while creating organizer', [
                'account_id' => $accountId,
            ]);
            return null;
        }

        return $defaultConfiguration->getId();
    }
}
