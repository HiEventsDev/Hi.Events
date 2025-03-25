<?php

namespace HiEvents\Services\Domain\Product;

use Exception;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Tax\DTO\TaxAndProductAssociateParams;
use HiEvents\Services\Domain\Tax\TaxAndProductAssociationService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\ProductEvent;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Throwable;

class CreateProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface      $productRepository,
        private readonly DatabaseManager                 $databaseManager,
        private readonly TaxAndProductAssociationService $taxAndProductAssociationService,
        private readonly ProductPriceCreateService       $priceCreateService,
        private readonly HtmlPurifierService             $purifier,
        private readonly EventRepositoryInterface        $eventRepository,
        private readonly ProductOrderingService          $productOrderingService,
        private readonly DomainEventDispatcherService    $domainEventDispatcherService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function createProduct(
        ProductDomainObject $product,
        int                 $accountId,
        ?array              $taxAndFeeIds = null,
    ): ProductDomainObject
    {
        return $this->databaseManager->transaction(function () use ($accountId, $taxAndFeeIds, $product) {
            $persistedProduct = $this->persistProduct($product);

            if ($taxAndFeeIds) {
                $this->associateTaxesAndFees($persistedProduct, $taxAndFeeIds, $accountId);
            }

            $product = $this->createProductPrices($persistedProduct, $product);

            $this->domainEventDispatcherService->dispatch(
                new ProductEvent(
                    type: DomainEventType::PRODUCT_CREATED,
                    productId: $product->getId(),
                )
            );

            return $product;
        });
    }

    private function persistProduct(ProductDomainObject $productsData): ProductDomainObject
    {
        $event = $this->eventRepository->findById($productsData->getEventId());

        return $this->productRepository->create([
            'title' => $productsData->getTitle(),
            'type' => $productsData->getType(),
            'product_type' => $productsData->getProductType(),
            'order' => $this->productOrderingService->getOrderForNewProduct(
                eventId: $productsData->getEventId(),
                productCategoryId: $productsData->getProductCategoryId(),
            ),
            'sale_start_date' => $productsData->getSaleStartDate()
                ? DateHelper::convertToUTC($productsData->getSaleStartDate(), $event->getTimezone())
                : null,
            'sale_end_date' => $productsData->getSaleEndDate()
                ? DateHelper::convertToUTC($productsData->getSaleEndDate(), $event->getTimezone())
                : null,
            'max_per_order' => $productsData->getMaxPerOrder(),
            'description' => $this->purifier->purify($productsData->getDescription()),
            'start_collapsed' => $productsData->getStartCollapsed(),
            'min_per_order' => $productsData->getMinPerOrder(),
            'is_hidden' => $productsData->getIsHidden(),
            'hide_before_sale_start_date' => $productsData->getHideBeforeSaleStartDate(),
            'hide_after_sale_end_date' => $productsData->getHideAfterSaleEndDate(),
            'hide_when_sold_out' => $productsData->getHideWhenSoldOut(),
            'show_quantity_remaining' => $productsData->getShowQuantityRemaining(),
            'is_hidden_without_promo_code' => $productsData->getIsHiddenWithoutPromoCode(),
            'event_id' => $productsData->getEventId(),
            'product_category_id' => $productsData->getProductCategoryId(),
        ]);
    }

    /**
     * @throws Exception
     */
    private function createProductTaxesAndFees(
        ProductDomainObject $product,
        array               $taxAndFeeIds,
        int                 $accountId,
    ): Collection
    {
        return $this->taxAndProductAssociationService->addTaxesToProduct(
            new TaxAndProductAssociateParams(
                productId: $product->getId(),
                accountId: $accountId,
                taxAndFeeIds: $taxAndFeeIds,
            ),
        );
    }

    /**
     * @throws Exception
     */
    private function associateTaxesAndFees(ProductDomainObject $persistedProduct, array $taxAndFeeIds, int $accountId): void
    {
        $persistedProduct->setTaxAndFees($this->createProductTaxesAndFees(
            product: $persistedProduct,
            taxAndFeeIds: $taxAndFeeIds,
            accountId: $accountId,
        ));
    }

    private function createProductPrices(ProductDomainObject $persistedProduct, ProductDomainObject $product): ProductDomainObject
    {
        $prices = $this->priceCreateService->createPrices(
            productId: $persistedProduct->getId(),
            prices: $product->getProductPrices(),
            event: $this->eventRepository->findById($product->getEventId()),
        );

        return $persistedProduct->setProductPrices($prices);
    }
}
