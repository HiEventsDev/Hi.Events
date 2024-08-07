<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\StripeCustomerDomainObject;
use HiEvents\Models\StripeCustomer;
use HiEvents\Repository\Interfaces\StripeCustomerRepositoryInterface;

class StripeCustomerRepository extends BaseRepository implements StripeCustomerRepositoryInterface
{
    protected function getModel(): string
    {
        return StripeCustomer::class;
    }

    public function getDomainObject(): string
    {
        return StripeCustomerDomainObject::class;
    }
}
