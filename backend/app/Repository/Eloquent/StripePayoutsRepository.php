<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\StripePayoutDomainObject;
use HiEvents\Models\StripePayout;
use HiEvents\Repository\Interfaces\StripePayoutsRepositoryInterface;

/**
 * @extends BaseRepository<StripePayoutDomainObject>
 */
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
