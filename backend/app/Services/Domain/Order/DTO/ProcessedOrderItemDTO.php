<?php

namespace HiEvents\Services\Domain\Order\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\OrderItemDomainObject;

class ProcessedOrderItemDTO extends BaseDataObject
{
    /**
     * @param  string[]  $seat_uids
     */
    public function __construct(
        public readonly OrderItemDomainObject $order_item,
        public readonly array $seat_uids,
    ) {}
}
