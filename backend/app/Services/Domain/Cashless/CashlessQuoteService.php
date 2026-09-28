<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Cashless\DTO\CashlessPurchaseItemRequestDTO;
use HiEvents\Services\Domain\Cashless\DTO\CashlessQuoteDTO;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Illuminate\Support\Collection;

class CashlessQuoteService
{
    public function __construct(
        private readonly TaxAndFeeCalculationService $taxAndFeeCalculationService,
        private readonly CashlessSettingsService $cashlessSettingsService,
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * @param  Collection<CashlessPurchaseItemRequestDTO>  $items
     */
    public function quoteBasket(CashlessSalesPointDomainObject $salesPoint, Collection $items): CashlessQuoteDTO
    {
        $subtotal = 0.0;
        $fees = 0.0;
        $taxes = 0.0;

        foreach ($items as $item) {
            $product = $salesPoint->getProducts()
                ?->first(fn (ProductDomainObject $candidate) => $candidate->getId() === $item->product_id);

            $price = $product?->getProductPrices()
                ?->first(fn (ProductPriceDomainObject $candidate) => $candidate->getId() === $item->product_price_id);

            if ($price === null) {
                continue;
            }

            $calculated = $this->taxAndFeeCalculationService->calculateTaxAndFeesForProduct(
                product: $product,
                price: $price->getPrice(),
                quantity: $item->quantity,
            );

            $subtotal += $price->getPrice() * $item->quantity;
            $fees += $calculated->feeTotal;
            $taxes += $calculated->taxTotal;
        }

        return $this->toQuote($subtotal, $fees, $taxes);
    }

    /**
     * @throws CashlessNotEnabledException
     */
    public function quoteTopup(int $eventId, float $amount): CashlessQuoteDTO
    {
        $settings = $this->cashlessSettingsService->getEnabledSettings($eventId);

        $product = $this->productRepository
            ->loadRelation(TaxAndFeesDomainObject::class)
            ->findFirstWhere([
                ProductDomainObjectAbstract::ID => $settings->getCashlessTopupProductId(),
            ]);

        if ($product === null) {
            return $this->toQuote($amount, 0.0, 0.0);
        }

        $calculated = $this->taxAndFeeCalculationService->calculateTaxAndFeesForProduct(
            product: $product,
            price: $amount,
        );

        return $this->toQuote($amount, $calculated->feeTotal, $calculated->taxTotal);
    }

    private function toQuote(float $subtotal, float $fees, float $taxes): CashlessQuoteDTO
    {
        return new CashlessQuoteDTO(
            subtotal: Currency::round($subtotal),
            fees: Currency::round($fees),
            taxes: Currency::round($taxes),
            total: Currency::round($subtotal + $fees + $taxes),
        );
    }
}
