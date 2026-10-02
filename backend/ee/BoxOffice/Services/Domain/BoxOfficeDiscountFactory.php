<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeDiscountDTO;

class BoxOfficeDiscountFactory
{
    public function fromDiscount(?BoxOfficeDiscountDTO $discount, int $eventId): ?PromoCodeDomainObject
    {
        if ($discount === null || $discount->value <= 0) {
            return null;
        }

        $isFixed = $discount->type === PromoCodeDiscountTypeEnum::FIXED->name;

        return (new PromoCodeDomainObject)
            ->setEventId($eventId)
            ->setCode('box-office')
            ->setDiscount($discount->value)
            ->setDiscountType($isFixed ? PromoCodeDiscountTypeEnum::FIXED->name : PromoCodeDiscountTypeEnum::PERCENTAGE->name)
            ->setDiscountAppliesTo($isFixed ? PromoCodeDiscountAppliesToEnum::ORDER->name : PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name)
            ->setApplicableProductIds(null);
    }
}
