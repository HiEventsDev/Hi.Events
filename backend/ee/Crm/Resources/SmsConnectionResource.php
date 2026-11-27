<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmSmsConnectionDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmSmsConnectionDomainObject
 */
class SmsConnectionResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'provider' => $this->getProvider(),
            'account_sid' => $this->getAccountSid(),
            'messaging_service_sid' => $this->getMessagingServiceSid(),
            /** @var array<int, 'US'|'GB'|'IE'|'AU'> */
            'enabled_countries' => array_values((array) $this->getEnabledCountries()),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
