<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Generated\ProductPriceDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Domain\Product\CreateProductService;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;
use HiEvents\Services\Domain\ProductCategory\GetProductCategoryService;
use Throwable;

class CreateProductHandler
{
    public function __construct(
        private readonly CreateProductService      $productCreateService,
        private readonly GetProductCategoryService $getProductCategoryService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpsertProductDTO $productsData): ProductDomainObject
    {
        $productPrices = $productsData->prices->map(fn(ProductPriceDTO $price) => ProductPriceDomainObject::hydrateFromArray([
            ProductPriceDomainObjectAbstract::PRICE => $productsData->type === ProductPriceType::FREE ? 0.00 : $price->price,
            ProductPriceDomainObjectAbstract::LABEL => $price->label,
            ProductPriceDomainObjectAbstract::SALE_START_DATE => $price->sale_start_date,
            ProductPriceDomainObjectAbstract::SALE_END_DATE => $price->sale_end_date,
            ProductPriceDomainObjectAbstract::INITIAL_QUANTITY_AVAILABLE => $price->initial_quantity_available,
            ProductPriceDomainObjectAbstract::IS_HIDDEN => $price->is_hidden,
        ]));

        $category = $this->getProductCategoryService->getCategory(
            categoryId: $productsData->product_category_id,
            eventId: $productsData->event_id
        );

        return $this->productCreateService->createProduct(
            product: (new ProductDomainObject())
                ->setTitle($productsData->title)
                ->setType($productsData->type->name)
                ->setOrder($productsData->order)
                ->setSaleStartDate($productsData->sale_start_date)
                ->setSaleEndDate($productsData->sale_end_date)
                ->setMaxPerOrder($productsData->max_per_order)
                ->setDescription($productsData->description)
                ->setMinPerOrder($productsData->min_per_order)
                ->setIsHidden($productsData->is_hidden)
                ->setStartCollapsed($productsData->start_collapsed)
                ->setHideBeforeSaleStartDate($productsData->hide_before_sale_start_date)
                ->setHideAfterSaleEndDate($productsData->hide_after_sale_end_date)
                ->setHideWhenSoldOut($productsData->hide_when_sold_out)
                ->setShowQuantityRemaining($productsData->show_quantity_remaining)
                ->setIsHiddenWithoutPromoCode($productsData->is_hidden_without_promo_code)
                ->setProductPrices($productPrices)
                ->setEventId($productsData->event_id)
                ->setProductType($productsData->product_type->name)
                ->setProductCategoryId($category->getId()),
            accountId: $productsData->account_id,
            taxAndFeeIds: $productsData->tax_and_fee_ids,
        );
    }
}
