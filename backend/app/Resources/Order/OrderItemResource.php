<?php

namespace TicketKitten\Resources\Order;

use Illuminate\Http\Request;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\Resources\BaseResource;

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
            'ticket_id' => $this->getTicketId(),
            'item_name' => $this->getItemName(),
            'taxes_and_fees_rollup' => $this->getTaxesAndFeesRollup(),
        ];
    }
}
