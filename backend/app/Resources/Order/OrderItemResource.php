<?php

namespace HiEvents\Resources\Order;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResource;
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
            $this->mergeWhen($this->isPermitted(Permission::VIEW_FINANCIALS), fn () => [
                'total_before_additions' => $this->getTotalBeforeAdditions(),
                'price' => $this->getPrice(),
                'price_before_discount' => $this->getPriceBeforeDiscount(),
                'taxes_and_fees_rollup' => $this->getTaxesAndFeesRollup(),
            ]),
            'quantity' => $this->getQuantity(),
            'product_id' => $this->getProductId(),
            'event_occurrence_id' => $this->getEventOccurrenceId(),
            'band_key' => $this->getBandKey(),
            'item_name' => $this->getItemName(),
            'event_occurrence' => $this->when(
                ! is_null($this->getEventOccurrence()),
                fn () => new EventOccurrenceResource($this->getEventOccurrence()),
            ),
        ];
    }
}
