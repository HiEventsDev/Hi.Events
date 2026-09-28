<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductCategoryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\CreateProductService;
use Illuminate\Support\Collection;
use Throwable;

class CashlessTopupProductProvisionService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductPriceRepositoryInterface $productPriceRepository,
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly CreateProductService $createProductService,
    ) {}

    /**
     * @throws Throwable
     */
    public function provision(
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        float $minimumTopupAmount,
    ): ProductDomainObject {
        $existingProduct = $this->findExistingProduct($eventSettings);

        if ($existingProduct !== null) {
            return $this->syncMinimumAmount($existingProduct, $minimumTopupAmount);
        }

        $product = $this->createProductService->createProduct(
            product: (new ProductDomainObject)
                ->setEventId($event->getId())
                ->setProductCategoryId($this->resolveCategoryId($event->getId()))
                ->setTitle(__('Cashless credit'))
                ->setDescription(__('Top up the balance attached to your ticket and pay cashless at the event.'))
                ->setType(ProductPriceType::DONATION->name)
                ->setProductType(ProductType::GENERAL->name)
                ->setIsHidden(true)
                ->setProductPrices(new Collection([
                    (new ProductPriceDomainObject)->setPrice($minimumTopupAmount),
                ])),
            accountId: $event->getAccountId(),
        );

        $this->productRepository->updateFromArray($product->getId(), [
            ProductDomainObjectAbstract::IS_CASHLESS_TOPUP => true,
        ]);

        $this->eventSettingsRepository->updateWhere(
            attributes: [EventSettingDomainObjectAbstract::CASHLESS_TOPUP_PRODUCT_ID => $product->getId()],
            where: [EventSettingDomainObjectAbstract::EVENT_ID => $event->getId()],
        );

        return $product;
    }

    private function findExistingProduct(EventSettingDomainObject $eventSettings): ?ProductDomainObject
    {
        if ($eventSettings->getCashlessTopupProductId() === null) {
            return null;
        }

        return $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->findFirstWhere([
                ProductDomainObjectAbstract::ID => $eventSettings->getCashlessTopupProductId(),
            ]);
    }

    private function syncMinimumAmount(ProductDomainObject $product, float $minimumTopupAmount): ProductDomainObject
    {
        $price = $product->getProductPrices()?->first();

        if ($price !== null && $price->getPrice() !== $minimumTopupAmount) {
            $this->productPriceRepository->updateFromArray($price->getId(), [
                'price' => $minimumTopupAmount,
            ]);
        }

        return $product;
    }

    private function resolveCategoryId(int $eventId): ?int
    {
        return $this->productCategoryRepository->findFirstWhere([
            ProductCategoryDomainObjectAbstract::EVENT_ID => $eventId,
        ])?->getId();
    }
}
