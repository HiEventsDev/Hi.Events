<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\EventStatisticDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventStatisticDomainObject
 */
class EventStatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'unique_views' => $this->getUniqueViews(),
            'total_views' => $this->getTotalViews(),
            'sales_total_gross' => $this->getSalesTotalGross(),
            'total_tax' => $this->getTotalTax(),
            'sales_total_before_additions' => $this->getSalesTotalBeforeAdditions(),
            'total_fee' => $this->getTotalFee(),
            'products_sold' => $this->getProductsSold(),
            'attendees_registered' => $this->getAttendeesRegistered(),
            'total_refunded' => $this->getTotalRefunded(),
        ];
    }
}
