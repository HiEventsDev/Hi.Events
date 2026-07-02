<?php

namespace HiEvents\Services\Application\Handlers\Admin\Organizer;

use HiEvents\DataTransferObjects\UpdateAdminOrganizerVatSettingDTO;
use HiEvents\DomainObjects\OrganizerVatSettingDomainObject;
use HiEvents\Repository\Interfaces\OrganizerVatSettingRepositoryInterface;

class UpdateAdminOrganizerVatSettingHandler
{
    public function __construct(
        private readonly OrganizerVatSettingRepositoryInterface $vatSettingRepository,
    ) {}

    public function handle(UpdateAdminOrganizerVatSettingDTO $dto): OrganizerVatSettingDomainObject
    {
        $existing = $this->vatSettingRepository->findByOrganizerId($dto->organizerId);

        $data = [
            'organizer_id' => $dto->organizerId,
            'vat_registered' => $dto->vatRegistered,
            'vat_number' => $dto->vatNumber,
            'vat_validated' => $dto->vatValidated ?? false,
            'business_name' => $dto->businessName,
            'business_address' => $dto->businessAddress,
            'vat_country_code' => $dto->vatCountryCode,
        ];

        if ($existing) {
            return $this->vatSettingRepository->updateFromArray(
                id: $existing->getId(),
                attributes: $data
            );
        }

        return $this->vatSettingRepository->create($data);
    }
}
