<?php

namespace HiEvents\Resources\Account;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountConfigurationDomainObject
 */
class AccountConfigurationResource extends JsonResource
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
            ],
        ];
    }
}
