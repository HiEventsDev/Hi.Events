<?php

namespace HiEvents\Services\Application\Handlers\Admin\Organizer;

use HiEvents\DataTransferObjects\UpdateOrganizerConfigurationDTO;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;

class UpdateOrganizerConfigurationHandler
{
    public function __construct(
        private readonly OrganizerConfigurationRepositoryInterface $configurationRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
    ) {}

    public function handle(UpdateOrganizerConfigurationDTO $dto): OrganizerConfigurationDomainObject
    {
        $organizer = $this->organizerRepository
            ->loadRelation(new Relationship(
                domainObject: OrganizerConfigurationDomainObject::class,
                name: 'organizer_configuration',
            ))
            ->findById($dto->organizerId);

        $currentConfiguration = $organizer->getOrganizerConfiguration();

        if ($currentConfiguration !== null
            && ! $currentConfiguration->isDefault()
            && $this->isConfigurationDedicatedTo($currentConfiguration->getId(), $organizer->getId())
        ) {
            return $this->configurationRepository->updateFromArray(
                id: $currentConfiguration->getId(),
                attributes: ['application_fees' => $dto->applicationFees],
            );
        }

        $configuration = $this->configurationRepository->create([
            'name' => sprintf('%s (#%d) - Custom Fees', $organizer->getName(), $organizer->getId()),
            'is_system_default' => false,
            'application_fees' => $dto->applicationFees,
        ]);

        $this->organizerRepository->updateFromArray(
            id: $organizer->getId(),
            attributes: ['organizer_configuration_id' => $configuration->getId()],
        );

        return $configuration;
    }

    private function isConfigurationDedicatedTo(int $configurationId, int $organizerId): bool
    {
        $totalReferences = $this->organizerRepository->countWhere([
            'organizer_configuration_id' => $configurationId,
        ]);

        if ($totalReferences !== 1) {
            return false;
        }

        $ownReference = $this->organizerRepository->countWhere([
            'organizer_configuration_id' => $configurationId,
            'id' => $organizerId,
        ]);

        return $ownReference === 1;
    }
}
