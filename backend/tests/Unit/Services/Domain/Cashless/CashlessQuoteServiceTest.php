<?php

namespace Tests\Unit\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessQuoteService;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\DTO\CashlessPurchaseItemRequestDTO;
use HiEvents\Services\Domain\Tax\DTO\TaxCalculationResponse;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CashlessQuoteServiceTest extends TestCase
{
    private TaxAndFeeCalculationService|MockInterface $taxCalculator;

    private CashlessSettingsService|MockInterface $settingsService;

    private ProductRepositoryInterface|MockInterface $productRepository;

    private CashlessQuoteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taxCalculator = Mockery::mock(TaxAndFeeCalculationService::class);
        $this->settingsService = Mockery::mock(CashlessSettingsService::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $this->service = new CashlessQuoteService(
            $this->taxCalculator,
            $this->settingsService,
            $this->productRepository,
        );
    }

    public function test_a_basket_quote_adds_taxes_and_fees_to_the_subtotal(): void
    {
        $product = (new ProductDomainObject)->setId(4);
        $price = (new ProductPriceDomainObject)->setId(9)->setPrice(5.00);
        $product->setProductPrices(new Collection([$price]));

        $salesPoint = (new CashlessSalesPointDomainObject)->setProducts(new Collection([$product]));

        $this->taxCalculator
            ->shouldReceive('calculateTaxAndFeesForProduct')
            ->once()
            ->with($product, 5.00, 3)
            ->andReturn(new TaxCalculationResponse(feeTotal: 0.0, taxTotal: 1.50, rollUp: []));

        $quote = $this->service->quoteBasket($salesPoint, new Collection([
            new CashlessPurchaseItemRequestDTO(product_id: 4, product_price_id: 9, quantity: 3),
        ]));

        $this->assertSame(15.00, $quote->subtotal);
        $this->assertSame(1.50, $quote->taxes);
        $this->assertSame(16.50, $quote->total);
    }

    public function test_a_topup_quote_includes_the_online_fee_the_terminal_must_be_charged(): void
    {
        $this->settingsService
            ->shouldReceive('getEnabledSettings')
            ->with(2)
            ->andReturn((new EventSettingDomainObject)->setCashlessTopupProductId(4));

        $product = (new ProductDomainObject)->setId(4);

        $this->productRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->productRepository->shouldReceive('findFirstWhere')->andReturn($product);

        $this->taxCalculator
            ->shouldReceive('calculateTaxAndFeesForProduct')
            ->once()
            ->with($product, 20.00)
            ->andReturn(new TaxCalculationResponse(feeTotal: 0.35, taxTotal: 0.0, rollUp: []));

        $quote = $this->service->quoteTopup(eventId: 2, amount: 20.00);

        $this->assertSame(20.00, $quote->subtotal);
        $this->assertSame(0.35, $quote->fees);
        $this->assertSame(20.35, $quote->total);
    }
}
