<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\Product\ProductPriceService;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Illuminate\Support\Collection;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class OrderItemProcessingService
{
    public function __construct(
        private readonly OrderRepositoryInterface    $orderRepository,
        private readonly ProductRepositoryInterface  $productRepository,
        private readonly TaxAndFeeCalculationService $taxCalculationService,
        private readonly ProductPriceService         $productPriceService,
    )
    {
    }

    /**
     * @param OrderDomainObject $order
     * @param Collection<ProductOrderDetailsDTO> $productsOrderDetails
     * @param EventDomainObject $event
     * @param PromoCodeDomainObject|null $promoCode
     * @return Collection
     */
    public function process(
        OrderDomainObject      $order,
        Collection             $productsOrderDetails,
        EventDomainObject      $event,
        ?PromoCodeDomainObject $promoCode
    ): Collection
    {
        $orderItems = collect();

        foreach ($productsOrderDetails as $productOrderDetail) {
            $product = $this->productRepository
                ->loadRelation(TaxAndFeesDomainObject::class)
                ->loadRelation(ProductPriceDomainObject::class)
                ->findFirstWhere([
                    ProductDomainObjectAbstract::ID => $productOrderDetail->product_id,
                    ProductDomainObjectAbstract::EVENT_ID => $event->getId(),
                ]);

            if ($product === null) {
                throw new ResourceNotFoundException(
                    __('Product with id :id not found', ['id' => $productOrderDetail->product_id])
                );
            }

            $productOrderDetail->quantities->each(function (OrderProductPriceDTO $productPrice) use ($promoCode, $order, $orderItems, $product) {
                if ($productPrice->quantity === 0) {
                    return;
                }
                $orderItemData = $this->calculateOrderItemData($product, $productPrice, $order, $promoCode);
                $orderItems->push($this->orderRepository->addOrderItem($orderItemData));
            });
        }

        return $orderItems;
    }

    private function calculateOrderItemData(
        ProductDomainObject    $product,
        OrderProductPriceDTO   $productPriceDetails,
        OrderDomainObject      $order,
        ?PromoCodeDomainObject $promoCode
    ): array
    {
        $prices = $this->productPriceService->getPrice($product, $productPriceDetails, $promoCode);
        $priceWithDiscount = $prices->price;
        $priceBeforeDiscount = $prices->price_before_discount;

        $itemTotalWithDiscount = $priceWithDiscount * $productPriceDetails->quantity;

        $taxesAndFees = $this->taxCalculationService->calculateTaxAndFeesForProduct(
            product: $product,
            price: $priceWithDiscount,
            quantity: $productPriceDetails->quantity
        );

        return [
            'product_type' => $product->getProductType(),
            'product_id' => $product->getId(),
            'product_price_id' => $productPriceDetails->price_id,
            'quantity' => $productPriceDetails->quantity,
            'price_before_discount' => $priceBeforeDiscount,
            'total_before_additions' => Currency::round($itemTotalWithDiscount),
            'price' => $priceWithDiscount,
            'order_id' => $order->getId(),
            'item_name' => $this->getOrderItemLabel($product, $productPriceDetails->price_id),
            'total_tax' => $taxesAndFees->taxTotal,
            'total_service_fee' => $taxesAndFees->feeTotal,
            'total_gross' => Currency::round($itemTotalWithDiscount + $taxesAndFees->taxTotal + $taxesAndFees->feeTotal),
            'taxes_and_fees_rollup' => $taxesAndFees->rollUp,
        ];
    }

    private function getOrderItemLabel(ProductDomainObject $product, int $priceId): string
    {
        if ($product->isTieredType()) {
            return $product->getTitle() . ' - ' . $product->getProductPrices()
                    ?->filter(fn($p) => $p->getId() === $priceId)->first()
                    ?->getLabel();
        }

        return $product->getTitle();
    }
}
