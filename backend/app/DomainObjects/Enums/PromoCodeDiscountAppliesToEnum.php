<?php

namespace HiEvents\DomainObjects\Enums;

enum PromoCodeDiscountAppliesToEnum
{
    use BaseEnum;

    case EACH_PRODUCT;
    case ORDER;
}
