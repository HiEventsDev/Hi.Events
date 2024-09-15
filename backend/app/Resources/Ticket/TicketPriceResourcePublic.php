<?php

namespace HiEvents\Resources\Ticket;

use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin TicketPriceDomainObject
 */
class TicketPriceResourcePublic extends BaseResource
{
    public const SHOW_QUANTITY_AVAILABLE = 'show_quantity_available';

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'label' => $this->getLabel(),
            'price' => $this->getPrice(),
            'sale_start_date' => $this->getSaleStartDate(),
            'sale_end_date' => $this->getSaleEndDate(),
            'price_including_taxes_and_fees' => $this->getPriceIncludingTaxAndServiceFee(),
            'price_before_discount' => $this->getPriceBeforeDiscount(),
            'is_discounted' => (bool)$this->getPriceBeforeDiscount(),
            'tax_total' => $this->getTaxTotal(),
            'fee_total' => $this->getFeeTotal(),
            'is_before_sale_start_date' => $this->isBeforeSaleStartDate(),
            'is_after_sale_end_date' => $this->isAfterSaleEndDate(),
            'is_available' => $this->isAvailable(),
            'is_sold_out' => $this->isSoldOut(),
            $this->mergeWhen($this->getAdditionalDataByKey(self::SHOW_QUANTITY_AVAILABLE), fn() => [
                'quantity_remaining' => $this->getQuantityAvailable(),
            ]),
        ];
    }
}
