<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Infrastructure\CurrencyConversion\CurrencyConversionClientInterface;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

class OrderApplicationFeeCalculationServiceTest extends TestCase
{
    private Repository $config;
    private CurrencyConversionClientInterface $currencyConversionClient;
    private OrderApplicationFeeCalculationService $service;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Repository::class);
        $this->currencyConversionClient = $this->createMock(CurrencyConversionClientInterface::class);
        $this->service = new OrderApplicationFeeCalculationService($this->config, $this->currencyConversionClient);
    }

    private function createOrderWithItems(array $items, string $currency = 'USD'): OrderDomainObject
    {
        $order = (new OrderDomainObject())
            ->setCurrency($currency)
            ->setOrderItems(collect($items));

        // Calculate gross manually for test accuracy
        $total = collect($items)->reduce(fn($carry, $item) => $carry + ($item->getPrice() * $item->getQuantity()), 0);
        $order->setTotalGross($total);

        return $order;
    }

    private function createItem(float $price, int $quantity): OrderItemDomainObject
    {
        $item = $this->createMock(OrderItemDomainObject::class);
        $item->method('getPrice')->willReturn($price);
        $item->method('getQuantity')->willReturn($quantity);
        return $item;
    }

    private function createAccountConfig(float $fixedFee = 0, float $percentageFee = 0): AccountConfigurationDomainObject
    {
        $config = $this->getMockBuilder(AccountConfigurationDomainObject::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFixedApplicationFee', 'getPercentageApplicationFee'])
            ->getMock();

        $config->method('getFixedApplicationFee')->willReturn($fixedFee);
        $config->method('getPercentageApplicationFee')->willReturn($percentageFee);

        return $config;
    }

    public function testNoFeeWhenSaasModeDisabled(): void
    {
        $this->config->method('get')->with('app.saas_mode_enabled')->willReturn(false);

        $order = $this->createOrderWithItems([$this->createItem(10, 2)]);
        $account = $this->createAccountConfig(1, 1);

        $fee = $this->service->calculateApplicationFee($account, $order);

        $this->assertEquals(0.0, $fee->toFloat());
    }

    public function testNoFeeForFreeOrder(): void
    {
        $this->config->method('get')->willReturn(true);

        $order = $this->createOrderWithItems([$this->createItem(0, 3)]);
        $account = $this->createAccountConfig(1, 1);

        $fee = $this->service->calculateApplicationFee($account, $order);

        $this->assertEquals(0.0, $fee->toFloat());
    }

    public function testFixedAndPercentageFeeSameCurrency(): void
    {
        $this->config->method('get')->willReturn(true);

        $order = $this->createOrderWithItems([
            $this->createItem(10, 1),  // chargeable
            $this->createItem(0, 5),   // free
            $this->createItem(20, 2),  // chargeable
        ]);

        $account = $this->createAccountConfig(2.00, 1); // 2 USD fixed, 1% percentage

        // 3 chargeable items × $2 fixed = $6
        // $10 + $40 = $50 gross → 1% of $50 = $0.50
        // Total = $6.50
        $fee = $this->service->calculateApplicationFee($account, $order);

        $this->assertEquals(6.50, $fee->toFloat());
    }

    public function testCurrencyConversionForFixedFee(): void
    {
        $this->config->method('get')->willReturn(true);

        $order = $this->createOrderWithItems([
            $this->createItem(15, 1), // chargeable
            $this->createItem(0, 3),  // free
        ], 'EUR');

        $account = $this->createAccountConfig(1.50, 20);

        $this->currencyConversionClient->method('convert')
            ->willReturn(MoneyValue::fromFloat(2.00, 'EUR')); // fixed fee per chargeable = €2.00

        // 1 chargeable × €2 fixed = €2
        // 15 × 20% = €3 percentage
        // Total = €5
        $fee = $this->service->calculateApplicationFee($account, $order);

        $this->assertEquals(5.00, $fee->toFloat());
    }
}
