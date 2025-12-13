<?php

namespace Tests\Unit\Services\Domain\Order;

use Brick\Money\Currency;
use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Services\Domain\Order\OrderPlatformFeePassThroughService;
use HiEvents\Services\Infrastructure\CurrencyConversion\CurrencyConversionClientInterface;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

class OrderPlatformFeePassThroughServiceTest extends TestCase
{
    private Repository $config;
    private CurrencyConversionClientInterface $currencyConversionClient;
    private OrderPlatformFeePassThroughService $service;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Repository::class);
        $this->currencyConversionClient = $this->createMock(CurrencyConversionClientInterface::class);

        $this->currencyConversionClient->method('convert')->willReturnCallback(
            fn(Currency $from, Currency $to, float $amount) => MoneyValue::fromFloat($amount, $to->getCurrencyCode())
        );

        $this->service = new OrderPlatformFeePassThroughService(
            $this->config,
            $this->currencyConversionClient
        );
    }

    private function createAccountConfig(float $fixedFee = 0.30, float $percentageFee = 2.9): AccountConfigurationDomainObject
    {
        $mock = $this->createMock(AccountConfigurationDomainObject::class);
        $mock->method('getFixedApplicationFee')->willReturn($fixedFee);
        $mock->method('getPercentageApplicationFee')->willReturn($percentageFee);
        return $mock;
    }

    private function createEventSettings(bool $passPlatformFeeToBuyer = true): EventSettingDomainObject
    {
        $settings = $this->createMock(EventSettingDomainObject::class);
        $settings->method('getPassPlatformFeeToBuyer')->willReturn($passPlatformFeeToBuyer);
        return $settings;
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

    public function testCalculatePlatformFeeReturnsZeroWhenDisabled(): void
    {
        $this->config->method('get')->with('app.saas_mode_enabled')->willReturn(true);

        $account = $this->createAccountConfig();
        $eventSettings = $this->createEventSettings(false);

        $result = $this->service->calculatePlatformFee($account, $eventSettings, 100.00, 1, 'USD');

        $this->assertEquals(0.0, $result);
    }

    public function testCalculatePlatformFeeReturnsZeroForZeroTotal(): void
    {
        $this->config->method('get')->willReturn(true);

        $account = $this->createAccountConfig();
        $eventSettings = $this->createEventSettings(true);

        $result = $this->service->calculatePlatformFee($account, $eventSettings, 0.0, 1, 'USD');

        $this->assertEquals(0.0, $result);
    }

    public function testCalculatePlatformFeeBasicCalculation(): void
    {
        $this->config->method('get')->willReturn(true);

        // 2.9% + $0.30 fixed fee
        $account = $this->createAccountConfig(0.30, 2.9);
        $eventSettings = $this->createEventSettings(true);

        // Total: $100
        // Formula: P = (0.30 + 100 * 0.029) / (1 - 0.029)
        // P = (0.30 + 2.90) / 0.971 = 3.20 / 0.971 = 3.30
        $result = $this->service->calculatePlatformFee($account, $eventSettings, 100.00, 1, 'USD');

        $this->assertEqualsWithDelta(3.30, $result, 0.01);
    }

    public function testCalculatePlatformFeeWithMultipleQuantity(): void
    {
        $this->config->method('get')->willReturn(true);

        // 2.9% + $0.30 fixed fee per item
        $account = $this->createAccountConfig(0.30, 2.9);
        $eventSettings = $this->createEventSettings(true);

        // 2 items, total: $200
        // Fixed fee = 0.30 * 2 = 0.60
        // Formula: P = (0.60 + 200 * 0.029) / (1 - 0.029)
        // P = (0.60 + 5.80) / 0.971 = 6.40 / 0.971 = 6.59
        $result = $this->service->calculatePlatformFee($account, $eventSettings, 200.00, 2, 'USD');

        $this->assertEqualsWithDelta(6.59, $result, 0.01);
    }

    public function testPlatformFeeExactlyCoversStripeApplicationFee(): void
    {
        $this->config->method('get')->willReturn(true);

        $fixedFee = 0.30;
        $percentageRate = 2.9;
        $account = $this->createAccountConfig($fixedFee, $percentageRate);
        $eventSettings = $this->createEventSettings(true);

        $totalBeforePlatformFee = 129.15;

        $platformFee = $this->service->calculatePlatformFee(
            $account,
            $eventSettings,
            $totalBeforePlatformFee,
            1,
            'USD'
        );

        // The new total that Stripe sees
        $newTotal = $totalBeforePlatformFee + $platformFee;

        // What Stripe would calculate as application fee
        $stripeAppFee = $fixedFee + ($newTotal * $percentageRate / 100);

        // Platform fee should equal Stripe app fee
        $this->assertEqualsWithDelta(
            $platformFee,
            $stripeAppFee,
            0.01,
            "Platform fee ({$platformFee}) should equal Stripe app fee ({$stripeAppFee})"
        );
    }

    public function testPlatformFeeWithDifferentTotals(): void
    {
        $this->config->method('get')->willReturn(true);

        $fixedFee = 0.30;
        $percentageRate = 2.9;
        $account = $this->createAccountConfig($fixedFee, $percentageRate);
        $eventSettings = $this->createEventSettings(true);

        $testCases = [
            ['total' => 50.00, 'desc' => '$50 order'],
            ['total' => 100.00, 'desc' => '$100 order'],
            ['total' => 250.00, 'desc' => '$250 order'],
            ['total' => 500.00, 'desc' => '$500 order'],
            ['total' => 1000.00, 'desc' => '$1000 order'],
        ];

        foreach ($testCases as $testCase) {
            $platformFee = $this->service->calculatePlatformFee(
                $account,
                $eventSettings,
                $testCase['total'],
                1,
                'USD'
            );

            $newTotal = $testCase['total'] + $platformFee;
            $stripeAppFee = $fixedFee + ($newTotal * $percentageRate / 100);

            $this->assertEqualsWithDelta(
                $platformFee,
                $stripeAppFee,
                0.01,
                "Failed for {$testCase['desc']}: Platform fee ({$platformFee}) != Stripe fee ({$stripeAppFee})"
            );
        }
    }

    public function testCurrencyConversionCalledForNonUsdCurrency(): void
    {
        $this->config->method('get')->willReturn(true);

        $currencyConversionClient = $this->createMock(CurrencyConversionClientInterface::class);

        $currencyConversionClient->expects($this->once())
            ->method('convert')
            ->with(
                $this->callback(fn(Currency $c) => $c->getCurrencyCode() === 'USD'),
                $this->callback(fn(Currency $c) => $c->getCurrencyCode() === 'EUR'),
                0.30
            )
            ->willReturn(MoneyValue::fromFloat(0.27, 'EUR'));

        $service = new OrderPlatformFeePassThroughService(
            $this->config,
            $currencyConversionClient
        );

        $account = $this->createAccountConfig(0.30, 2.9);
        $eventSettings = $this->createEventSettings(true);

        $result = $service->calculatePlatformFee($account, $eventSettings, 100.00, 1, 'EUR');

        $this->assertGreaterThan(0, $result);
    }

    public function testNoCurrencyConversionForUsd(): void
    {
        $this->config->method('get')->willReturn(true);

        $currencyConversionClient = $this->createMock(CurrencyConversionClientInterface::class);
        $currencyConversionClient->expects($this->never())->method('convert');

        $service = new OrderPlatformFeePassThroughService(
            $this->config,
            $currencyConversionClient
        );

        $account = $this->createAccountConfig(0.30, 2.9);
        $eventSettings = $this->createEventSettings(true);

        $result = $service->calculatePlatformFee($account, $eventSettings, 100.00, 1, 'USD');

        $this->assertGreaterThan(0, $result);
    }

    public function testZeroFixedFeeOnlyPercentage(): void
    {
        $this->config->method('get')->willReturn(true);

        $account = $this->createAccountConfig(0.0, 2.9);
        $eventSettings = $this->createEventSettings(true);

        $platformFee = $this->service->calculatePlatformFee($account, $eventSettings, 100.00, 1, 'USD');

        $this->assertGreaterThan(0, $platformFee);

        // Verify Stripe coverage
        $newTotal = 100.00 + $platformFee;
        $stripeAppFee = 0.0 + ($newTotal * 2.9 / 100);
        $this->assertEqualsWithDelta($platformFee, $stripeAppFee, 0.01);
    }

    public function testZeroPercentageOnlyFixedFee(): void
    {
        $this->config->method('get')->willReturn(true);

        $account = $this->createAccountConfig(0.50, 0.0);
        $eventSettings = $this->createEventSettings(true);

        $platformFee = $this->service->calculatePlatformFee($account, $eventSettings, 100.00, 1, 'USD');

        // With 0% percentage, formula simplifies to just the fixed fee
        $this->assertEquals(0.50, $platformFee);
    }

    public function testBothFeesZero(): void
    {
        $this->config->method('get')->willReturn(true);

        $account = $this->createAccountConfig(0.0, 0.0);
        $eventSettings = $this->createEventSettings(true);

        $platformFee = $this->service->calculatePlatformFee($account, $eventSettings, 100.00, 1, 'USD');

        $this->assertEquals(0.0, $platformFee);
    }
}
