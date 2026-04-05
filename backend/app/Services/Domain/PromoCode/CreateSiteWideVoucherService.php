<?php

namespace HiEvents\Services\Domain\PromoCode;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;

class CreateSiteWideVoucherService
{
    public function __construct(
        private readonly PromoCodeRepositoryInterface $promoCodeRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function createVoucher(
        int                       $accountId,
        string                    $code,
        PromoCodeDiscountTypeEnum $discountType,
        ?float                    $discount,
        ?string                   $expiryDate,
        ?int                      $maxAllowedUsages,
        ?string                   $validFrom = null,
        ?string                   $message = null,
    ): PromoCodeDomainObject
    {
        $existing = $this->promoCodeRepository->findSiteWideByCode($code, $accountId);

        if ($existing !== null) {
            throw new ResourceConflictException(
                __('Voucher code :code already exists', ['code' => $code]),
            );
        }

        return $this->promoCodeRepository->create([
            PromoCodeDomainObjectAbstract::ACCOUNT_ID => $accountId,
            PromoCodeDomainObjectAbstract::EVENT_ID => null,
            PromoCodeDomainObjectAbstract::CODE => $code,
            PromoCodeDomainObjectAbstract::DISCOUNT => $discountType === PromoCodeDiscountTypeEnum::NONE
                ? 0.00
                : $discount,
            PromoCodeDomainObjectAbstract::DISCOUNT_TYPE => $discountType->name,
            PromoCodeDomainObjectAbstract::EXPIRY_DATE => $expiryDate,
            PromoCodeDomainObjectAbstract::VALID_FROM => $validFrom,
            PromoCodeDomainObjectAbstract::MAX_ALLOWED_USAGES => $maxAllowedUsages,
            PromoCodeDomainObjectAbstract::APPLICABLE_PRODUCT_IDS => null,
            PromoCodeDomainObjectAbstract::MESSAGE => $message,
        ]);
    }
}
