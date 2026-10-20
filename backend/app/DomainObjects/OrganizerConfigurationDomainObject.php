<?php

namespace HiEvents\DomainObjects;

class OrganizerConfigurationDomainObject extends Generated\OrganizerConfigurationDomainObjectAbstract
{
    public function getFixedApplicationFee(): float
    {
        return $this->getApplicationFees()['fixed'] ?? config('app.default_application_fee_fixed');
    }

    public function getPercentageApplicationFee(): float
    {
        return $this->getApplicationFees()['percentage'] ?? config('app.default_application_fee_percentage');
    }

    public function getApplicationFeeCurrency(): string
    {
        return $this->getApplicationFees()['currency'] ?? 'USD';
    }

    public function isDefault(): bool
    {
        return $this->getIsSystemDefault() || $this->getDefaultForCurrency() !== null;
    }
}
