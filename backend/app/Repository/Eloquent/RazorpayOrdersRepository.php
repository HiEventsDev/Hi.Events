<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\Models\RazorpayOrder;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;

/**
 * @extends BaseRepository<RazorpayOrderDomainObject>
 */
class RazorpayOrdersRepository extends BaseRepository implements RazorpayOrdersRepositoryInterface
{
    protected function getModel(): string
    {
        return RazorpayOrder::class;
    }

    public function getDomainObject(): string
    {
        return RazorpayOrderDomainObject::class;
    }
}
