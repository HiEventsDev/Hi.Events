<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\DomainObjects\CashlessTransactionItemDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin CashlessTransactionItemDomainObject
 */
class CashlessTransactionItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->getProductId(),
            'product_title' => $this->getProductTitle(),
            'unit_price' => $this->getUnitPrice(),
            'quantity' => $this->getQuantity(),
            'total' => $this->getTotal(),
        ];
    }
}
