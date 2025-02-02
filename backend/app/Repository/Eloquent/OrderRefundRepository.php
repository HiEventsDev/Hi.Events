<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrderRefundDomainObject;
use HiEvents\Models\OrderRefund;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;

class OrderRefundRepository extends BaseRepository implements OrderRefundRepositoryInterface
{
    protected function getModel(): string
    {
        return OrderRefund::class;
    }

    public function getDomainObject(): string
    {
        return OrderRefundDomainObject::class;
    }
}
