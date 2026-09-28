<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Product\ProductResourcePublic;
use Illuminate\Http\Request;

/**
 * @mixin CashlessSalesPointDomainObject
 */
class CashlessSalesPointResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'short_id' => $this->getShortId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'requires_pin' => $this->getAccessPin() !== null,
            'allow_staff_topups' => $this->getAllowStaffTopups(),
            'currency' => $this->getEvent()?->getCurrency(),
            'event_title' => $this->getEvent()?->getTitle(),
            'products' => $this->when(
                $this->getProducts() !== null,
                fn () => ProductResourcePublic::collection($this->getProducts()),
            ),
        ];
    }
}
