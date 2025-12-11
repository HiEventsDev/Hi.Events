<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Services\Domain\Order\DTO\ApplicationFeeValuesDTO;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Order\OrderPlatformFeePassThroughService;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

class OrderPlatformFeePassThroughServiceTest extends TestCase
{
    private Repository $config;
    private OrderApplicationFeeCalculationService $applicationFeeCalculationService;
    private OrderPlatformFeePassThroughService $service;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Repository::class);
        $this->applicationFeeCalculationService = $this->createMock(OrderApplicationFeeCalculationService::class);
        $this->service = new OrderPlatformFeePassThroughService(
            $this->config,
            $this->applicationFeeCalculationService,
        );
    }

    private function createOrderItem(float $price, int $quantity, float $totalGross): OrderItemDomainObject
    {
        $item = $this->createMock(OrderItemDomainObject::class);
        $item->method('getPrice')->willReturn($price);
        $item->method('getQuantity')->willReturn($quantity);
        $item->method('getTotalGross')->willReturn($totalGross);
        return $item;
    }

    private function createAccountConfig(): AccountConfigurationDomainObject
    {
        return $this->createMock(AccountConfigurationDomainObject::class);
    }

    private function createEventSettings(bool $passPlatformFeeToBuyer = true): EventSettingDomainObject
    {
        $settings = $this->createMock(EventSettingDomainObject::class);
        $settings->method('getPassPlatformFeeToBuyer')->willReturn($passPlatformFeeToBuyer);
        return $settings;
    }

    private function createApplicationFeeDTO(float $amount, string $currency = 'USD'): ApplicationFeeValuesDTO
    {
        return new ApplicationFeeValuesDTO(
            grossApplicationFee: MoneyValue::fromFloat($amount, $currency),
            netApplicationFee: MoneyValue::fromFloat($amount, $currency),
        );
    }

    public function testIsEnabledReturnsFalseWhenSaasModeDisabled(): void
    {
        $this->config->method('get')->with('app.saas_mode_enabled')->willReturn(false);

        $eventSettings = $this->createEventSettings(true);

        $this->assertFalse($this->service->isEnabled($eventSettings));
    }

    public function testIsEnabledReturnsFalseWhenEventSettingDisabled(): void
    {
        $this->config->method('get')->with('app.saas_mode_enabled')->willReturn(true);

        $eventSettings = $this->createEventSettings(false);

        $this->assertFalse($this->service->isEnabled($eventSettings));
    }

    public function testIsEnabledReturnsTrueWhenBothEnabled(): void
    {
        $this->config->method('get')->with('app.saas_mode_enabled')->willReturn(true);

        $eventSettings = $this->createEventSettings(true);

        $this->assertTrue($this->service->isEnabled($eventSettings));
    }

    public function testCalculateForProductPriceReturnsZeroWhenDisabled(): void
    {
        $this->config->method('get')->with('app.saas_mode_enabled')->willReturn(true);

        $account = $this->createAccountConfig();
        $eventSettings = $this->createEventSettings(false);

        $fee = $this->service->calculateForProductPrice($account, $eventSettings, 100.00, 'USD');

        $this->assertEquals(0.0, $fee);
    }

    public function testCalculateForProductPriceReturnsZeroForZeroPrice(): void
    {
        $this->config->method('get')->willReturn(true);

        $account = $this->createAccountConfig();
        $eventSettings = $this->createEventSettings(true);

        $fee = $this->service->calculateForProductPrice($account, $eventSettings, 0.0, 'USD');

        $this->assertEquals(0.0, $fee);
    }

    public function testCalculateForProductPriceDelegatesToApplicationFeeService(): void
    {
        $this->config->method('get')->willReturn(true);

        $account = $this->createAccountConfig();
        $eventSettings = $this->createEventSettings(true);

        $this->applicationFeeCalculationService
            ->expects($this->once())
            ->method('calculateApplicationFee')
            ->willReturn($this->createApplicationFeeDTO(5.50));

        $fee = $this->service->calculateForProductPrice($account, $eventSettings, 100.00, 'USD');

        $this->assertEquals(5.50, $fee);
    }

    public function testCalculateForOrderReturnsZeroWhenDisabled(): void
    {
        $this->config->method('get')->with('app.saas_mode_enabled')->willReturn(true);

        $orderItems = collect([$this->createOrderItem(10, 2, 20)]);
        $account = $this->createAccountConfig();
        $eventSettings = $this->createEventSettings(false);

        $fee = $this->service->calculateForOrder($account, $eventSettings, $orderItems, 'USD');

        $this->assertEquals(0.0, $fee);
    }

    public function testCalculateForOrderDelegatesToApplicationFeeService(): void
    {
        $this->config->method('get')->willReturn(true);

        $orderItems = collect([
            $this->createOrderItem(10, 1, 10),
            $this->createOrderItem(20, 2, 40),
        ]);

        $account = $this->createAccountConfig();
        $eventSettings = $this->createEventSettings(true);

        $this->applicationFeeCalculationService
            ->expects($this->once())
            ->method('calculateApplicationFee')
            ->willReturn($this->createApplicationFeeDTO(6.50));

        $fee = $this->service->calculateForOrder($account, $eventSettings, $orderItems, 'USD');

        $this->assertEquals(6.50, $fee);
    }

    public function testCalculateForOrderReturnsZeroWhenApplicationFeeServiceReturnsNull(): void
    {
        $this->config->method('get')->willReturn(true);

        $orderItems = collect([$this->createOrderItem(10, 1, 10)]);
        $account = $this->createAccountConfig();
        $eventSettings = $this->createEventSettings(true);

        $this->applicationFeeCalculationService
            ->method('calculateApplicationFee')
            ->willReturn(null);

        $fee = $this->service->calculateForOrder($account, $eventSettings, $orderItems, 'USD');

        $this->assertEquals(0.0, $fee);
    }
}
