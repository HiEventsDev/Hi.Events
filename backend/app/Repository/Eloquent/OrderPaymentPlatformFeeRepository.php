<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrderPaymentPlatformFeeDomainObject;
use HiEvents\Models\OrderPaymentPlatformFee;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;

class OrderPaymentPlatformFeeRepository extends BaseRepository implements OrderPaymentPlatformFeeRepositoryInterface
{
    protected function getModel(): string
    {
        return OrderPaymentPlatformFee::class;
    }

    public function getDomainObject(): string
    {
        return OrderPaymentPlatformFeeDomainObject::class;
    }
}
