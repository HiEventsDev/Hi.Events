<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Product\ProductResource;
use Illuminate\Http\Request;

/**
 * @mixin CashlessSalesPointDomainObject
 */
class CashlessSalesPointResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'short_id' => $this->getShortId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'has_access_pin' => $this->getAccessPin() !== null,
            'allow_staff_topups' => $this->getAllowStaffTopups(),
            'activates_at' => $this->getActivatesAt(),
            'expires_at' => $this->getExpiresAt(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            $this->mergeWhen($this->getSalesTotal() !== null, fn () => [
                'sales_total' => $this->getSalesTotal(),
                'transaction_count' => $this->getTransactionCount(),
            ]),
            'products' => $this->when(
                $this->getProducts() !== null,
                fn () => ProductResource::collection($this->getProducts()),
            ),
        ];
    }
}
