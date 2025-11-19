<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Account\Vat;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Vat\DTO\UpsertAccountVatSettingDTO;
use HiEvents\Services\Infrastructure\Vat\ViesValidationService;

class UpsertAccountVatSettingHandler
{
    public function __construct(
        private readonly AccountVatSettingRepositoryInterface $vatSettingRepository,
        private readonly ViesValidationService $viesValidationService,
    ) {
    }

    public function handle(UpsertAccountVatSettingDTO $command): AccountVatSettingDomainObject
    {
        $existing = $this->vatSettingRepository->findByAccountId($command->accountId);

        $data = [
            'account_id' => $command->accountId,
            'vat_registered' => $command->vatRegistered,
        ];

        if ($command->vatRegistered && $command->vatNumber) {
            $vatNumber = strtoupper(trim($command->vatNumber));

            if (preg_match('/^[A-Z]{2}[0-9A-Z]{8,15}$/', $vatNumber)) {
                $validationResult = $this->viesValidationService->validateVatNumber($vatNumber);

                $data['vat_number'] = $vatNumber;
                $data['vat_validated'] = $validationResult->valid;
                $data['vat_country_code'] = $validationResult->countryCode;
                $data['business_name'] = $validationResult->businessName;
                $data['business_address'] = $validationResult->businessAddress;

                if ($validationResult->valid) {
                    $data['vat_validation_date'] = now();
                }
            } else {
                $data['vat_number'] = $vatNumber;
                $data['vat_validated'] = false;
                $data['vat_country_code'] = substr($vatNumber, 0, 2);
                $data['business_name'] = null;
                $data['business_address'] = null;
            }
        } else {
            $data['vat_number'] = null;
            $data['vat_validated'] = false;
            $data['vat_country_code'] = null;
            $data['business_name'] = null;
            $data['business_address'] = null;
            $data['vat_validation_date'] = null;
        }

        if ($existing) {
            return $this->vatSettingRepository->updateFromArray(
                id: $existing->getId(),
                attributes: $data
            );
        }

        return $this->vatSettingRepository->create($data);
    }
}
