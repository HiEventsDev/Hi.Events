<?php

namespace Tests\Unit\Services\Domain\Payment;

use HiEvents\DomainObjects\Enums\OfflinePaymentAvailability;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Domain\Payment\OfflinePaymentEligibilityService;
use Mockery;
use Tests\TestCase;

class OfflinePaymentEligibilityServiceTest extends TestCase
{
    public function test_returns_false_when_offline_not_enabled_for_event(): void
    {
        $service = new OfflinePaymentEligibilityService(Mockery::mock(PromoCodeRepositoryInterface::class));

        $order = (new OrderDomainObject)->setPromoCodeId(1);
        $settings = (new EventSettingDomainObject)
            ->setPaymentProviders([PaymentProviders::STRIPE->value])
            ->setOfflinePaymentAvailability(OfflinePaymentAvailability::EVERYONE->name);

        $this->assertFalse($service->isEligible($order, $settings));
    }

    public function test_returns_true_when_everyone_and_offline_enabled(): void
    {
        $promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $promoCodeRepository->shouldNotReceive('findById');

        $service = new OfflinePaymentEligibilityService($promoCodeRepository);

        $order = (new OrderDomainObject)->setPromoCodeId(null);
        $settings = (new EventSettingDomainObject)
            ->setPaymentProviders([PaymentProviders::STRIPE->value, PaymentProviders::OFFLINE->value])
            ->setOfflinePaymentAvailability(OfflinePaymentAvailability::EVERYONE->name);

        $this->assertTrue($service->isEligible($order, $settings));
    }

    public function test_promo_code_only_rejects_without_promo_code(): void
    {
        $service = new OfflinePaymentEligibilityService(Mockery::mock(PromoCodeRepositoryInterface::class));

        $order = (new OrderDomainObject)->setPromoCodeId(null);
        $settings = (new EventSettingDomainObject)
            ->setPaymentProviders([PaymentProviders::OFFLINE->value])
            ->setOfflinePaymentAvailability(OfflinePaymentAvailability::PROMO_CODE_ONLY->name);

        $this->assertFalse($service->isEligible($order, $settings));
    }

    public function test_promo_code_only_rejects_ordinary_promo_code(): void
    {
        $promoCode = (new PromoCodeDomainObject)->setAllowsOfflinePayment(false);

        $promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $promoCodeRepository->shouldReceive('findById')->once()->with(10)->andReturn($promoCode);

        $service = new OfflinePaymentEligibilityService($promoCodeRepository);

        $order = (new OrderDomainObject)->setPromoCodeId(10);
        $settings = (new EventSettingDomainObject)
            ->setPaymentProviders([PaymentProviders::OFFLINE->value])
            ->setOfflinePaymentAvailability(OfflinePaymentAvailability::PROMO_CODE_ONLY->name);

        $this->assertFalse($service->isEligible($order, $settings));
    }

    public function test_promo_code_only_accepts_authorised_promo_code(): void
    {
        $promoCode = (new PromoCodeDomainObject)->setAllowsOfflinePayment(true);

        $promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $promoCodeRepository->shouldReceive('findById')->once()->with(11)->andReturn($promoCode);

        $service = new OfflinePaymentEligibilityService($promoCodeRepository);

        $order = (new OrderDomainObject)->setPromoCodeId(11);
        $settings = (new EventSettingDomainObject)
            ->setPaymentProviders([PaymentProviders::STRIPE->value, PaymentProviders::OFFLINE->value])
            ->setOfflinePaymentAvailability(OfflinePaymentAvailability::PROMO_CODE_ONLY->name);

        $this->assertTrue($service->isEligible($order, $settings));
    }

    public function test_promo_code_only_rejects_missing_promo_code_record(): void
    {
        $promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $promoCodeRepository->shouldReceive('findById')->once()->with(99)->andReturn(null);

        $service = new OfflinePaymentEligibilityService($promoCodeRepository);

        $order = (new OrderDomainObject)->setPromoCodeId(99);
        $settings = (new EventSettingDomainObject)
            ->setPaymentProviders([PaymentProviders::OFFLINE->value])
            ->setOfflinePaymentAvailability(OfflinePaymentAvailability::PROMO_CODE_ONLY->name);

        $this->assertFalse($service->isEligible($order, $settings));
    }
}
