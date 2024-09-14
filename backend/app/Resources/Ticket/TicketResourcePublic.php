<?php

namespace HiEvents\Resources\Ticket;

use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Resources\Tax\TaxAndFeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketDomainObject
 */
class TicketResourcePublic extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'type' => $this->getType(),
            'description' => $this->getDescription(),
            'max_per_order' => $this->getMaxPerOrder(),
            'min_per_order' => $this->getMinPerOrder(),
            'sale_start_date' => $this->getSaleStartDate(),
            'sale_end_date' => $this->getSaleEndDate(),
            'event_id' => $this->getEventId(),
            'is_before_sale_start_date' => $this->isBeforeSaleStartDate(),
            'is_after_sale_end_date' => $this->isAfterSaleEndDate(),
            $this->mergeWhen($this->getShowQuantityRemaining(), fn() => [
                'quantity_available' => $this->getQuantityAvailable(),
            ]),
            'price' => $this->when(
                $this->getTicketPrices() && !$this->isTieredType(),
                fn() => $this->getPrice(),
            ),
            'prices' => $this->when(
                (bool)$this->getTicketPrices(),
                fn() => TicketPriceResourcePublic::collectionWithAdditionalData($this->getTicketPrices(), [
                    TicketPriceResourcePublic::SHOW_QUANTITY_AVAILABLE => $this->getShowQuantityRemaining(),
                ]),
            ),
            'taxes' => $this->when(
                (bool)$this->getTaxAndFees(),
                fn() => TaxAndFeeResource::collection($this->getTaxAndFees())
            ),
            $this->mergeWhen((bool)$this->getTicketPrices(), fn() => [
                'is_available' => $this->isAvailable(),
                'is_sold_out' => $this->isSoldOut(),
            ]),
        ];
    }
}
