<?php

namespace HiEvents\Resources\Product;

use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseSummaryDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductPurchaseSummaryDTO
 */
class ProductPurchaseSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sold_quantity' => $this->soldQuantity,
            'awaiting_payment_quantity' => $this->awaitingPaymentQuantity,
            'cancelled_quantity' => $this->cancelledQuantity,
            'buyer_count' => $this->buyerCount,
            'gross_sales' => $this->grossSales,
            'refunded_order_count' => $this->refundedOrderCount,
        ];
    }
}
