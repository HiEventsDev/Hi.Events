<?php

namespace HiEvents\Services\Domain\PromoCode;

use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;

class PromoCodeUsageValidationService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    )
    {
    }

    /**
     * Usage is derived from a live count of orders currently holding the code
     * (completed, awaiting offline payment, or unexpired reservations), because the
     * persisted order_usage_count counter is only updated asynchronously after completion.
     */
    public function isPromoCodeUsable(?PromoCodeDomainObject $promoCode): bool
    {
        if (!$promoCode?->isValid()) {
            return false;
        }

        $maxAllowedUsages = $promoCode->getMaxAllowedUsages();

        if ($maxAllowedUsages === null) {
            return true;
        }

        return $this->orderRepository->countActivePromoCodeUsage($promoCode->getId()) < $maxAllowedUsages;
    }
}
