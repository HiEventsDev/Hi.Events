<?php

namespace HiEvents\Resources\Ticket;

use Illuminate\Http\Request;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin TicketPriceDomainObject
 */
class TicketPriceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'label' => $this->getLabel(),
            'price' => $this->getPrice(),
            'sale_start_date' => $this->getSaleStartDate(),
            'sale_end_date' => $this->getSaleEndDate(),
            'is_before_sale_start_date' => $this->isBeforeSaleStartDate(),
            'is_after_sale_end_date' => $this->isAfterSaleEndDate(),
            'is_available' => $this->isAvailable(),
            'initial_quantity_available' => $this->getInitialQuantityAvailable(),
            'quantity_sold' => $this->getQuantitySold(),
            'is_sold_out' => $this->isSoldOut(),
            'is_hidden' => $this->getIsHidden(),
            'off_sale_reason' => $this->getOffSaleReason(),
            'price_including_taxes_and_fees' => $this->getPriceIncludingTaxAndServiceFee(),
        ];
    }
}
