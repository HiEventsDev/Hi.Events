<?php

namespace Tests\Unit\Services\Domain\PromoCode;

use Carbon\Carbon;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\PromoCode\PromoCodeUsageValidationService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PromoCodeUsageValidationServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const PROMO_CODE_ID = 5;

    public function test_null_promo_code_is_not_usable(): void
    {
        $service = new PromoCodeUsageValidationService(Mockery::mock(OrderRepositoryInterface::class));

        $this->assertFalse($service->isPromoCodeUsable(null));
    }

    public function test_expired_promo_code_is_not_usable(): void
    {
        $promoCode = (new PromoCodeDomainObject)
            ->setId(self::PROMO_CODE_ID)
            ->setExpiryDate(Carbon::now()->subDay()->toDateTimeString());

        $orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $orderRepository->shouldNotReceive('countActivePromoCodeUsage');

        $service = new PromoCodeUsageValidationService($orderRepository);

        $this->assertFalse($service->isPromoCodeUsable($promoCode));
    }

    public function test_promo_code_with_no_usage_limit_is_usable(): void
    {
        $promoCode = (new PromoCodeDomainObject)
            ->setId(self::PROMO_CODE_ID)
            ->setMaxAllowedUsages(null);

        $orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $orderRepository->shouldNotReceive('countActivePromoCodeUsage');

        $service = new PromoCodeUsageValidationService($orderRepository);

        $this->assertTrue($service->isPromoCodeUsable($promoCode));
    }

    public function test_promo_code_is_usable_when_live_count_is_under_limit(): void
    {
        $promoCode = (new PromoCodeDomainObject)
            ->setId(self::PROMO_CODE_ID)
            ->setMaxAllowedUsages(2)
            ->setOrderUsageCount(0);

        $orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $orderRepository->shouldReceive('countActivePromoCodeUsage')
            ->with(self::PROMO_CODE_ID)
            ->andReturn(1);

        $service = new PromoCodeUsageValidationService($orderRepository);

        $this->assertTrue($service->isPromoCodeUsable($promoCode));
    }

    public function test_promo_code_is_not_usable_when_live_count_reaches_limit(): void
    {
        // Stale order_usage_count is 0 (passes isValid), but the live count has reached the limit.
        $promoCode = (new PromoCodeDomainObject)
            ->setId(self::PROMO_CODE_ID)
            ->setMaxAllowedUsages(1)
            ->setOrderUsageCount(0);

        $orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $orderRepository->shouldReceive('countActivePromoCodeUsage')
            ->with(self::PROMO_CODE_ID)
            ->andReturn(1);

        $service = new PromoCodeUsageValidationService($orderRepository);

        $this->assertFalse($service->isPromoCodeUsable($promoCode));
    }
}
