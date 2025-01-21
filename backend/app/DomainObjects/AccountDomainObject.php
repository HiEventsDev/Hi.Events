<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\DTO\AccountApplicationFeeDTO;

class AccountDomainObject extends Generated\AccountDomainObjectAbstract
{
    public function getApplicationFee(): AccountApplicationFeeDTO
    {
        $applicationFee = $this->getConfiguration()['application_fee'];

        return new AccountApplicationFeeDTO(
            $applicationFee['percentage'],
            $applicationFee['fixed']
        );
    }
}
