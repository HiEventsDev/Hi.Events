<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Services\Domain\Cashless\DTO\CashlessPurchaseItemRequestDTO;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CashlessSalesPointCatalogueService
{
    /**
     * @param  Collection<CashlessPurchaseItemRequestDTO>  $items
     *
     * @throws ValidationException
     */
    public function assertSellableAtSalesPoint(
        CashlessSalesPointDomainObject $salesPoint,
        Collection $items,
    ): void {
        $items->each(function (CashlessPurchaseItemRequestDTO $item) use ($salesPoint) {
            $product = $salesPoint->getProducts()
                ?->first(fn (ProductDomainObject $product) => $product->getId() === $item->product_id);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'items' => __('That product is not sold at this sales point.'),
                ]);
            }

            if ($product->getProductType() !== ProductType::GENERAL->name || $product->getIsCashlessTopup()) {
                throw ValidationException::withMessages([
                    'items' => __('Only general products can be sold at a cashless sales point.'),
                ]);
            }
        });
    }
}
