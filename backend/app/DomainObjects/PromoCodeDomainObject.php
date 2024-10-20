<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;

class PromoCodeDomainObject extends Generated\PromoCodeDomainObjectAbstract implements IsSortable
{
    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::CREATED_AT => [
                    'asc' => __('Oldest First'),
                    'desc' => __('Newest First'),
                ],
                self::CODE => [
                    'asc' => __('Code Name A-Z'),
                    'desc' => __('Code Name Z-A'),
                ],
                self::ORDER_USAGE_COUNT => [
                    'asc' => __('Usage Count Ascending'),
                    'desc' => __('Usage Count Descending'),
                ],
            ],
        );
    }

    public static function getDefaultSort(): string
    {
        return self::CREATED_AT;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'desc';
    }

    public function isDiscountCode(): bool
    {
        return $this->getDiscountType() !== null && $this->getDiscount() > 0;
    }

    public function isValid(): bool
    {
        if ($this->getExpiryDate() !== null && (new Carbon($this->getExpiryDate()))->isPast()) {
            return false;
        }

        if ($this->getMaxAllowedUsages() !== null && ($this->getOrderUsageCount() >= $this->getMaxAllowedUsages())) {
            return false;
        }

        return true;
    }

    public function appliesToProduct(ProductDomainObject $product): bool
    {
        // If there's no product IDs we apply the promo to all products
        if (!$this->getApplicableProductIds()) {
            return true;
        }

        return in_array($product->getId(), array_map('intval', $this->getApplicableProductIds()), true);
    }

    public function isFixedDiscount(): bool
    {
        return $this->getDiscountType() === PromoCodeDiscountTypeEnum::FIXED->name;
    }

    public function isPercentageDiscount(): bool
    {
        return $this->getDiscountType() === PromoCodeDiscountTypeEnum::PERCENTAGE->name;
    }

    public function isNoDiscountCode(): bool
    {
        return $this->getDiscountType() === PromoCodeDiscountTypeEnum::NONE->name;
    }
}
