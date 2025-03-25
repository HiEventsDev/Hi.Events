<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Product;

use Exception;
use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Exceptions\CannotChangeProductTypeException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Domain\Product\ProductPriceUpdateService;
use HiEvents\Services\Domain\ProductCategory\GetProductCategoryService;
use HiEvents\Services\Domain\Tax\DTO\TaxAndProductAssociateParams;
use HiEvents\Services\Domain\Tax\TaxAndProductAssociationService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\ProductEvent;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * @todo - Move logic into a domain service
 */
class EditProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface      $productRepository,
        private readonly TaxAndProductAssociationService $taxAndProductAssociationService,
        private readonly DatabaseManager                 $databaseManager,
        private readonly ProductPriceUpdateService       $priceUpdateService,
        private readonly HtmlPurifierService             $purifier,
        private readonly EventRepositoryInterface        $eventRepository,
        private readonly GetProductCategoryService       $getProductCategoryService,
        private readonly DomainEventDispatcherService    $domainEventDispatcherService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpsertProductDTO $productsData): DomainObjectInterface
    {
        return $this->databaseManager->transaction(function () use ($productsData) {
            $where = [
                'event_id' => $productsData->event_id,
                'id' => $productsData->product_id,
            ];

            $product = $this->updateProduct($productsData, $where);

            $this->addTaxes($product, $productsData);

            $this->priceUpdateService->updatePrices(
                $product,
                $productsData,
                $product->getProductPrices(),
                $this->eventRepository->findById($productsData->event_id)
            );

            $this->domainEventDispatcherService->dispatch(
                new ProductEvent(
                    type: DomainEventType::PRODUCT_UPDATED,
                    productId: $product->getId(),
                )
            );

            return $this->productRepository
                ->loadRelation(ProductPriceDomainObject::class)
                ->findById($product->getId());
        });
    }

    /**
     * @throws CannotChangeProductTypeException
     */
    private function updateProduct(UpsertProductDTO $productsData, array $where): ProductDomainObject
    {
        $event = $this->eventRepository->findById($productsData->event_id);

        $this->validateChangeInProductType($productsData);

        $productCategory = $this->getProductCategoryService->getCategory(
            $productsData->product_category_id,
            $productsData->event_id,
        );

        $this->productRepository->updateWhere(
            attributes: [
                'title' => $productsData->title,
                'type' => $productsData->type->name,
                'sale_start_date' => $productsData->sale_start_date
                    ? DateHelper::convertToUTC($productsData->sale_start_date, $event->getTimezone())
                    : null,
                'sale_end_date' => $productsData->sale_end_date
                    ? DateHelper::convertToUTC($productsData->sale_end_date, $event->getTimezone())
                    : null,
                'max_per_order' => $productsData->max_per_order,
                'description' => $this->purifier->purify($productsData->description),
                'min_per_order' => $productsData->min_per_order,
                'is_hidden' => $productsData->is_hidden,
                'start_collapsed' => $productsData->start_collapsed,
                'hide_before_sale_start_date' => $productsData->hide_before_sale_start_date,
                'hide_after_sale_end_date' => $productsData->hide_after_sale_end_date,
                'hide_when_sold_out' => $productsData->hide_when_sold_out,
                'show_quantity_remaining' => $productsData->show_quantity_remaining,
                'is_hidden_without_promo_code' => $productsData->is_hidden_without_promo_code,
                'product_type' => $productsData->product_type->name,
                'product_category_id' => $productCategory->getId(),
            ],
            where: $where
        );

        return $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->findFirstWhere($where);
    }

    /**
     * @throws Exception
     */
    private function addTaxes(ProductDomainObject $product, UpsertProductDTO $productsData): void
    {
        $this->taxAndProductAssociationService->addTaxesToProduct(
            new TaxAndProductAssociateParams(
                productId: $product->getId(),
                accountId: $productsData->account_id,
                taxAndFeeIds: $productsData->tax_and_fee_ids,
            )
        );
    }

    /**
     * @throws CannotChangeProductTypeException
     * @todo - We should probably check reserved products here as well
     */
    private function validateChangeInProductType(UpsertProductDTO $productsData): void
    {
        $product = $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->findById($productsData->product_id);

        $quantitySold = $product->getProductPrices()
            ->sum(fn(ProductPriceDomainObject $price) => $price->getQuantitySold());

        if ($product->getType() !== $productsData->type->name && $quantitySold > 0) {
            throw new CannotChangeProductTypeException(
                __('Product type cannot be changed as products have been registered for this type')
            );
        }
    }
}
