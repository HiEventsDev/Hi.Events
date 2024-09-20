<?php

namespace HiEvents\Resources\Order;

use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Product\ProductResourcePublic;
use Illuminate\Http\Request;

/**
 * @mixin OrderItemDomainObject
 */
class OrderItemResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'order_id' => $this->getOrderId(),
            'total_before_additions' => $this->getTotalBeforeAdditions(),
            'total_before_discount' => $this->getTotalBeforeDiscount(),
            'price' => $this->getPrice(),
            'price_before_discount' => $this->getPriceBeforeDiscount(),
            'quantity' => $this->getQuantity(),
            'product_id' => $this->getProductId(),
            'product_price_id' => $this->getProductPriceId(),
            'item_name' => $this->getItemName(),
            'total_service_fee' => $this->getTotalServiceFee(),
            'total_tax' => $this->getTotalTax(),
            'total_gross' => $this->getTotalGross(),
            'taxes_and_fees_rollup' => $this->getTaxesAndFeesRollup(),
            'product' => $this->when((bool)$this->getProduct(), fn() => new ProductResourcePublic($this->getProduct())),
        ];
    }
}
