<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DataTransferObjects\UpdateAdminAccountVatSettingDTO;
use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;

class UpdateAdminAccountVatSettingHandler
{
    public function __construct(
        private readonly AccountVatSettingRepositoryInterface $vatSettingRepository,
    )
    {
    }

    public function handle(UpdateAdminAccountVatSettingDTO $dto): AccountVatSettingDomainObject
    {
        $existing = $this->vatSettingRepository->findByAccountId($dto->accountId);

        $data = [
            'account_id' => $dto->accountId,
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
