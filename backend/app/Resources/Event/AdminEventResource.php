<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin EventDomainObject
 */
class AdminEventResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $statistics = $this->getEventStatistics();

        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'start_date' => $this->getStartDate(),
            'end_date' => $this->getEndDate(),
            'status' => $this->getStatus(),
            'organizer_name' => $this->getOrganizer()?->getName(),
            'organizer_id' => $this->getOrganizerId(),
            'account_name' => $this->getAccount()?->getName(),
            'account_id' => $this->getAccountId(),
            'user_id' => $this->getUserId(),
            'slug' => $this->getSlug(),
            'statistics' => $statistics ? [
                'total_gross_sales' => $statistics->getSalesTotalGross(),
                'products_sold' => $statistics->getProductsSold(),
                'attendees_registered' => $statistics->getAttendeesRegistered(),
                'orders_created' => $statistics->getOrdersCreated(),
                'orders_cancelled' => $statistics->getOrdersCancelled(),
            ] : null,
        ];
    }
}
