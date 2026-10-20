<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use Illuminate\Support\Collection;

class PublicProductSelectionFilter
{
    /**
     * @param  Collection<int, ProductCategoryDomainObject>  $categories
     * @param  int[]  $productIds
     * @param  int[]  $categoryIds
     * @return Collection<int, ProductCategoryDomainObject>
     */
    public function filter(Collection $categories, array $productIds, array $categoryIds): Collection
    {
        if ($productIds === [] && $categoryIds === []) {
            return $categories;
        }

        $isSelectedCategory = static fn (ProductCategoryDomainObject $category): bool => $categoryIds === []
            || in_array($category->getId(), $categoryIds, true);

        $selectedProducts = $categories
            ->filter($isSelectedCategory)
            ->flatMap(static fn (ProductCategoryDomainObject $category) => $category->getProducts() ?? collect())
            ->filter(static fn (ProductDomainObject $product) => $productIds === [] || in_array($product->getId(), $productIds, true));

        $keptProductIds = $selectedProducts
            ->flatMap(static fn (ProductDomainObject $product) => [$product->getId(), ...($product->getAddonProductIds() ?? [])])
            ->unique()
            ->all();

        return $categories
            ->each(static fn (ProductCategoryDomainObject $category) => $category->setProducts(
                ($category->getProducts() ?? collect())
                    ->filter(static fn (ProductDomainObject $product) => in_array($product->getId(), $keptProductIds, true))
                    ->values()
            ))
            ->filter(static fn (ProductCategoryDomainObject $category) => $category->getProducts()->isNotEmpty()
                || ($productIds === [] && $isSelectedCategory($category)))
            ->values();
    }
}
