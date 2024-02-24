<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Models\StripePayment;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;

class StripePaymentsRepository extends BaseRepository implements StripePaymentsRepositoryInterface
{
    protected function getModel(): string
    {
        return StripePayment::class;
    }

    public function getDomainObject(): string
    {
        return StripePaymentDomainObject::class;
    }
}
