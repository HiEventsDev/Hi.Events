<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin EventStatisticDomainObject
 */
class EventStatisticsResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'unique_views' => $this->getUniqueViews(),
            'total_views' => $this->getTotalViews(),
            'products_sold' => $this->getProductsSold(),
            'attendees_registered' => $this->getAttendeesRegistered(),
            $this->mergeWhen($this->isPermitted(Permission::VIEW_FINANCIALS), fn () => [
                'sales_total_gross' => $this->getSalesTotalGross(),
                'total_tax' => $this->getTotalTax(),
                'sales_total_before_additions' => $this->getSalesTotalBeforeAdditions(),
                'total_fee' => $this->getTotalFee(),
                'total_refunded' => $this->getTotalRefunded(),
            ]),
        ];
    }
}
