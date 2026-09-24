<?php

namespace HiEvents\Services\Domain\Payment;

use HiEvents\DomainObjects\Enums\OfflinePaymentAvailability;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;

class OfflinePaymentEligibilityService
{
    public function __construct(
        private readonly PromoCodeRepositoryInterface $promoCodeRepository,
    ) {}

    public function isEligible(
        OrderDomainObject $order,
        EventSettingDomainObject $settings,
    ): bool {
        if (collect($settings->getPaymentProviders())->contains(PaymentProviders::OFFLINE->value) === false) {
            return false;
        }

        $availability = $settings->getOfflinePaymentAvailability()
            ?? OfflinePaymentAvailability::EVERYONE->name;

        if ($availability === OfflinePaymentAvailability::EVERYONE->name) {
            return true;
        }

        $promoCodeId = $order->getPromoCodeId();
        if ($promoCodeId === null) {
            return false;
        }

        $promoCode = $this->promoCodeRepository->findById($promoCodeId);
        if ($promoCode === null) {
            return false;
        }

        return (bool) $promoCode->getAllowsOfflinePayment();
    }
}
