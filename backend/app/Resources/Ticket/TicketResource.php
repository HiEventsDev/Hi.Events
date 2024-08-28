<?php

namespace HiEvents\Resources\Ticket;

use HiEvents\DomainObjects\Enums\TicketType;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Resources\Tax\TaxAndFeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketDomainObject
 */
class TicketResource extends JsonResource
{
    public const DEFAULT_MIN_TICKETS = 1;

    public const DEFAULT_MAX_TICKETS = 10;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'type' => $this->getType(),
            'order' => $this->getOrder(),
            'description' => $this->getDescription(),
            'price' => $this->when(
                $this->getType() !== TicketType::TIERED->name,
                fn() => $this->getPrice()
            ),
            'max_per_order' => $this->getMaxPerOrder() ?? self::DEFAULT_MAX_TICKETS,
            'min_per_order' => $this->getMinPerOrder() ?? self::DEFAULT_MIN_TICKETS,
            'quantity_sold' => $this->getQuantitySold(),
            'sale_start_date' => $this->getSaleStartDate(),
            'sale_end_date' => $this->getSaleEndDate(),
            'event_id' => $this->getEventId(),
            'initial_quantity_available' => $this->getInitialQuantityAvailable(),
            'hide_before_sale_start_date' => $this->getHideBeforeSaleStartDate(),
            'hide_after_sale_end_date' => $this->getHideAfterSaleEndDate(),
            'show_quantity_remaining' => $this->getShowQuantityRemaining(),
            'hide_when_sold_out' => $this->getHideWhenSoldOut(),
            'is_hidden_without_promo_code' => $this->getIsHiddenWithoutPromoCode(),
            'is_hidden' => $this->getIsHidden(),
            'is_before_sale_start_date' => $this->isBeforeSaleStartDate(),
            'is_after_sale_end_date' => $this->isAfterSaleEndDate(),
            'is_available' => $this->isAvailable(),
            $this->mergeWhen((bool)$this->getTicketPrices(), fn() => [
                'is_sold_out' => $this->isSoldOut(),
            ]),
            'taxes_and_fees' => $this->when(
                (bool)$this->getTaxAndFees(),
                fn() => TaxAndFeeResource::collection($this->getTaxAndFees())
            ),
            'prices' => $this->when(
                (bool)$this->getTicketPrices(),
                fn() => TicketPriceResource::collection($this->getTicketPrices())
            ),
        ];
    }
}
