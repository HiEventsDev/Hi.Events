<?php

namespace HiEvents\Repository\DTO\ProductPurchase;

use HiEvents\DataTransferObjects\BaseDataObject;

class ProductPurchaseSummaryDTO extends BaseDataObject
{
    public function __construct(
        public int $soldQuantity,
        public int $awaitingPaymentQuantity,
        public int $cancelledQuantity,
        public int $buyerCount,
        public float $grossSales,
        public int $refundedOrderCount,
    ) {}
}
