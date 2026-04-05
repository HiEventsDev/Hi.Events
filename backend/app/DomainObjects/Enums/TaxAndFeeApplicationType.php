<?php

namespace HiEvents\DomainObjects\Enums;

enum TaxAndFeeApplicationType
{
    use BaseEnum;

    case PER_PRODUCT;
    case PER_ORDER;
}
