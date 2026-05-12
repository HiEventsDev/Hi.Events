<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Organizer\Vat;

use HiEvents\DomainObjects\OrganizerVatSettingDomainObject;
use HiEvents\DomainObjects\Status\VatValidationStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Vat\ValidateVatNumberJob;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerVatSettingRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\Vat\DTO\UpsertOrganizerVatSettingDTO;
use HiEvents\Services\Infrastructure\Vat\ViesValidationService;
use Psr\Log\LoggerInterface;

class UpsertOrganizerVatSettingHandler
{
    public function __construct(
        private readonly OrganizerVatSettingRepositoryInterface $vatSettingRepository,
        private readonly OrganizerRepositoryInterface           $organizerRepository,
        private readonly ViesValidationService                  $viesValidationService,
        private readonly LoggerInterface                        $logger,
    )
    {
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(UpsertOrganizerVatSettingDTO $command): OrganizerVatSettingDomainObject
    {
        $organizer = $this->organizerRepository->findFirstWhere([
            'id' => $command->organizerId,
            'account_id' => $command->accountId,
        ]);

        if ($organizer === null) {
            throw new ResourceNotFoundException(__('Organizer not found.'));
        }

        $existing = $this->vatSettingRepository->findByOrganizerId($command->organizerId);

        $data = [
            'organizer_id' => $command->organizerId,
            'vat_registered' => $command->vatRegistered,
        ];

        $shouldValidate = false;
        $vatNumber = null;

        if ($command->vatRegistered && $command->vatNumber) {
            $vatNumber = strtoupper(trim($command->vatNumber));

            if (preg_match('/^[A-Z]{2}[0-9A-Z]{8,15}$/', $vatNumber)) {
                $vatNumberChanged = !$existing || $existing->getVatNumber() !== $vatNumber;

                $data['vat_number'] = $vatNumber;
                $data['vat_country_code'] = substr($vatNumber, 0, 2);

                if ($vatNumberChanged) {
                    $shouldValidate = true;
                    $data = $this->trySyncValidation($vatNumber, $data);
                }
            } else {
                $data['vat_number'] = $vatNumber;
                $data['vat_validated'] = false;
                $data['vat_validation_status'] = VatValidationStatus::INVALID->value;
                $data['vat_validation_error'] = __('Invalid VAT number format');
                $data['vat_country_code'] = substr($vatNumber, 0, 2);
                $data['business_name'] = null;
                $data['business_address'] = null;
            }
        } else {
            $data['vat_number'] = null;
            $data['vat_validated'] = false;
            $data['vat_validation_status'] = VatValidationStatus::PENDING->value;
            $data['vat_validation_error'] = null;
            $data['vat_validation_attempts'] = 0;
            $data['vat_country_code'] = null;
            $data['business_name'] = null;
            $data['business_address'] = null;
            $data['vat_validation_date'] = null;
        }

        if ($existing) {
            $vatSetting = $this->vatSettingRepository->updateFromArray(
                id: $existing->getId(),
                attributes: $data
            );
        } else {
            $vatSetting = $this->vatSettingRepository->create($data);
        }

        if ($shouldValidate && $data['vat_validation_status'] === VatValidationStatus::PENDING->value) {
            $this->logger->info('Sync validation failed, dispatching VAT validation job', [
                'organizer_vat_setting_id' => $vatSetting->getId(),
                'organizer_id' => $command->organizerId,
                'vat_number_masked' => $this->maskVatNumber($vatNumber),
            ]);

            ValidateVatNumberJob::dispatch(
                $vatSetting->getId(),
                $vatNumber,
            );
        }

        return $vatSetting;
    }

    private function trySyncValidation(string $vatNumber, array $data): array
    {
        $this->logger->info('Attempting sync VAT validation', [
            'vat_number_masked' => $this->maskVatNumber($vatNumber),
        ]);

        $result = $this->viesValidationService->validateVatNumber($vatNumber);

        if ($result->valid) {
            $data['vat_validated'] = true;
            $data['vat_validation_status'] = VatValidationStatus::VALID->value;
            $data['vat_validation_error'] = null;
            $data['vat_validation_attempts'] = 1;
            $data['business_name'] = $result->businessName;
            $data['business_address'] = $result->businessAddress;
            $data['vat_validation_date'] = now();

            return $data;
        }

        if ($result->isTransientError) {
            $data['vat_validated'] = false;
            $data['vat_validation_status'] = VatValidationStatus::PENDING->value;
            $data['vat_validation_error'] = $result->errorMessage;
            $data['vat_validation_attempts'] = 1;
            $data['business_name'] = null;
            $data['business_address'] = null;
            $data['vat_validation_date'] = null;

            return $data;
        }

        $data['vat_validated'] = false;
        $data['vat_validation_status'] = VatValidationStatus::INVALID->value;
        $data['vat_validation_error'] = $result->errorMessage;
        $data['vat_validation_attempts'] = 1;
        $data['business_name'] = null;
        $data['business_address'] = null;
        $data['vat_validation_date'] = null;

        return $data;
    }

    private function maskVatNumber(string $vatNumber): string
    {
        $length = strlen($vatNumber);
        if ($length <= 4) {
            return $vatNumber;
        }

        return substr($vatNumber, 0, 2) . str_repeat('*', $length - 4) . substr($vatNumber, -2);
    }
}
