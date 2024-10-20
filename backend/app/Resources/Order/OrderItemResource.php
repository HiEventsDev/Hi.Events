<?php

namespace HiEvents\Resources\Order;

use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin OrderItemDomainObject
 */
class OrderItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'order_id' => $this->getOrderId(),
            'total_before_additions' => $this->getTotalBeforeAdditions(),
            'price' => $this->getPrice(),
            'quantity' => $this->getQuantity(),
            'product_id' => $this->getProductId(),
            'item_name' => $this->getItemName(),
            'price_before_discount' => $this->getPriceBeforeDiscount(),
            'taxes_and_fees_rollup' => $this->getTaxesAndFeesRollup(),
        ];
    }
}
