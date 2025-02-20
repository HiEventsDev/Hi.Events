<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrderApplicationFeeDomainObject;
use HiEvents\Models\OrderApplicationFee;
use HiEvents\Repository\Interfaces\OrderApplicationFeeRepositoryInterface;

class OrderApplicationFeeRepository extends BaseRepository implements OrderApplicationFeeRepositoryInterface
{
    protected function getModel(): string
    {
        return OrderApplicationFee::class;
    }

    public function getDomainObject(): string
    {
        return OrderApplicationFeeDomainObject::class;
    }
}
