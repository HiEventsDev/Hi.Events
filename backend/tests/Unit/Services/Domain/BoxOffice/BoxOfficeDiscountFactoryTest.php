<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeDiscountDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeDiscountFactory;
use Tests\TestCase;

class BoxOfficeDiscountFactoryTest extends TestCase
{
    public function test_fixed_discount_is_order_level(): void
    {
        $promo = (new BoxOfficeDiscountFactory)->fromDiscount(new BoxOfficeDiscountDTO(type: 'FIXED', value: 5.0), 3);

        $this->assertSame(5.0, $promo->getDiscount());
        $this->assertSame(PromoCodeDiscountTypeEnum::FIXED->name, $promo->getDiscountType());
        $this->assertSame(PromoCodeDiscountAppliesToEnum::ORDER->name, $promo->getDiscountAppliesTo());
        $this->assertTrue($promo->isOrderLevelDiscount());
    }

    public function test_percentage_discount_applies_per_product(): void
    {
        $promo = (new BoxOfficeDiscountFactory)->fromDiscount(new BoxOfficeDiscountDTO(type: 'PERCENTAGE', value: 10.0), 3);

        $this->assertTrue($promo->isPercentageDiscount());
        $this->assertFalse($promo->isOrderLevelDiscount());
    }

    public function test_zero_or_missing_discount_yields_null(): void
    {
        $factory = new BoxOfficeDiscountFactory;

        $this->assertNull($factory->fromDiscount(null, 3));
        $this->assertNull($factory->fromDiscount(new BoxOfficeDiscountDTO(type: 'FIXED', value: 0), 3));
    }
}
