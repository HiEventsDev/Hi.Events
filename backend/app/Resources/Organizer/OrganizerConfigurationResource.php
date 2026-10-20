<?php

namespace HiEvents\Resources\Organizer;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizerConfigurationDomainObject
 */
class OrganizerConfigurationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'is_system_default' => $this->getIsSystemDefault(),
            'default_for_currency' => $this->getDefaultForCurrency(),
            'application_fees' => [
                'percentage' => $this->getPercentageApplicationFee(),
                'fixed' => $this->getFixedApplicationFee(),
                'currency' => $this->getApplicationFeeCurrency(),
            ],
            'bypass_application_fees' => $this->getBypassApplicationFees(),
        ];
    }
}
