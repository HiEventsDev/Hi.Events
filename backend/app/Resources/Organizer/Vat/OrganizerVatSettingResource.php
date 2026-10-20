<?php

declare(strict_types=1);

namespace HiEvents\Resources\Organizer\Vat;

use HiEvents\DomainObjects\OrganizerVatSettingDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin OrganizerVatSettingDomainObject
 */
class OrganizerVatSettingResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'organizer_id' => $this->getOrganizerId(),
            'vat_registered' => $this->getVatRegistered(),
            'vat_number' => $this->getVatNumber(),
            'vat_validated' => $this->getVatValidated(),
            'vat_validation_status' => $this->getVatValidationStatus(),
            'vat_validation_error' => $this->getVatValidationError(),
            'vat_validation_attempts' => $this->getVatValidationAttempts(),
            'vat_validation_date' => $this->getVatValidationDate(),
            'business_name' => $this->getBusinessName(),
            'business_address' => $this->getBusinessAddress(),
            'vat_country_code' => $this->getVatCountryCode(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
