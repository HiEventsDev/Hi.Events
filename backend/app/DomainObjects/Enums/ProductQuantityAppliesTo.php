<?php

namespace HiEvents\DomainObjects\Enums;

enum ProductQuantityAppliesTo
{
    use BaseEnum;

    case OCCURRENCE;
    case EVENT;

    public static function defaultFor(ProductType $productType): self
    {
        return $productType === ProductType::TICKET ? self::OCCURRENCE : self::EVENT;
    }
}
