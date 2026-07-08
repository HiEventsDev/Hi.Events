<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrderRefundDomainObject;

/**
 * @extends RepositoryInterface<OrderRefundDomainObject>
 */
interface OrderRefundRepositoryInterface extends RepositoryInterface
{
    public function getTotalRefundedForOrder(int $orderId): float;
}
