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
            'application_fees' => [
                'percentage' => $this->getPercentageApplicationFee(),
                'fixed' => $this->getFixedApplicationFee(),
                'currency' => $this->getApplicationFeeCurrency(),
            ],
            'features' => (object) $this->getFeaturesArray(),
            'upgrades_to_id' => $this->getUpgradesToId(),
            'upgrade' => $this->when(
                $this->getUpgradesTo() !== null,
                fn () => [
                    'id' => $this->getUpgradesTo()->getId(),
                    'name' => $this->getUpgradesTo()->getName(),
                    'application_fees' => [
                        'percentage' => $this->getUpgradesTo()->getPercentageApplicationFee(),
                        'fixed' => $this->getUpgradesTo()->getFixedApplicationFee(),
                        'currency' => $this->getUpgradesTo()->getApplicationFeeCurrency(),
                    ],
                    'features' => (object) $this->getUpgradesTo()->getFeaturesArray(),
                ],
            ),
            'downgrade' => $this->when(
                $this->getDowngradeTarget() !== null,
                fn () => [
                    'id' => $this->getDowngradeTarget()->getId(),
                    'name' => $this->getDowngradeTarget()->getName(),
                    'application_fees' => [
                        'percentage' => $this->getDowngradeTarget()->getPercentageApplicationFee(),
                        'fixed' => $this->getDowngradeTarget()->getFixedApplicationFee(),
                        'currency' => $this->getDowngradeTarget()->getApplicationFeeCurrency(),
                    ],
                ],
            ),
            'bypass_application_fees' => $this->getBypassApplicationFees(),
        ];
    }
}
