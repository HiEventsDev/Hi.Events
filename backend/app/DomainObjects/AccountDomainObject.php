<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\DTO\AccountApplicationFeeDTO;

class AccountDomainObject extends Generated\AccountDomainObjectAbstract
{
    private ?AccountConfigurationDomainObject $configuration = null;

    public function getApplicationFee(): AccountApplicationFeeDTO
    {
        /** @var AccountConfigurationDomainObject $applicationFee */
        $applicationFee = $this->getConfiguration();

        return new AccountApplicationFeeDTO(
            $applicationFee->getPercentageApplicationFee(),
            $applicationFee->getFixedApplicationFee()
        );
    }

    public function getConfiguration(): ?AccountConfigurationDomainObject
    {
        return $this->configuration;
    }

    public function setConfiguration(AccountConfigurationDomainObject $configuration): void
    {
        $this->configuration = $configuration;
    }
}
