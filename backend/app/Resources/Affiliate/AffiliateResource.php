<?php

declare(strict_types=1);

namespace HiEvents\Resources\Affiliate;

use HiEvents\DomainObjects\AffiliateDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin AffiliateDomainObject
 */
class AffiliateResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'account_id' => $this->getAccountId(),
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'email' => $this->getEmail(),
            'total_sales' => $this->getTotalSales(),
            'total_sales_gross' => $this->getTotalSalesGross(),
            'status' => $this->getStatus(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
