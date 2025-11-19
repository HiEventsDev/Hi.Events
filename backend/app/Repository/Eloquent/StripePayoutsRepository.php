<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\Repository\Interfaces\StripePayoutsRepositoryInterface;
use HiEvents\Models\StripePayout;
use HiEvents\DomainObjects\StripePayoutDomainObject;

class StripePayoutsRepository extends BaseRepository implements StripePayoutsRepositoryInterface
{
    protected function getModel(): string
    {
        return StripePayout::class;
    }

    public function getDomainObject(): string
    {
        return StripePayoutDomainObject::class;
    }
}
