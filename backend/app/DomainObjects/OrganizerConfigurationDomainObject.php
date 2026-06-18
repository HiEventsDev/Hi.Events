<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\Feature;
use Illuminate\Support\Collection;

class OrganizerConfigurationDomainObject extends Generated\OrganizerConfigurationDomainObjectAbstract
{
    private ?OrganizerConfigurationDomainObject $upgradesTo = null;

    private ?Collection $downgradeOptions = null;

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

    public function hasFeature(Feature $feature): bool
    {
        return ($this->getFeaturesArray()[$feature->value] ?? false) === true;
    }

    public function getFeaturesArray(): array
    {
        $features = $this->getFeatures();

        if (is_string($features)) {
            return json_decode($features, true, 512, JSON_THROW_ON_ERROR) ?? [];
        }

        return $features ?? [];
    }

    public function setUpgradesTo(?OrganizerConfigurationDomainObject $upgradesTo): self
    {
        $this->upgradesTo = $upgradesTo;

        return $this;
    }

    public function getUpgradesTo(): ?OrganizerConfigurationDomainObject
    {
        return $this->upgradesTo;
    }

    public function setDowngradeOptions(?Collection $downgradeOptions): self
    {
        $this->downgradeOptions = $downgradeOptions;

        return $this;
    }

    public function getDowngradeOptions(): ?Collection
    {
        return $this->downgradeOptions;
    }

    public function getDowngradeTarget(): ?OrganizerConfigurationDomainObject
    {
        $options = $this->downgradeOptions;

        if ($options === null || $options->isEmpty()) {
            return null;
        }

        if ($options->count() === 1) {
            return $options->first();
        }

        return $options->first(
            fn (OrganizerConfigurationDomainObject $option) => $option->getIsSystemDefault()
        );
    }
}
