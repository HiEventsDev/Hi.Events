<?php

namespace HiEvents\DomainObjects\Enums;

enum TaxCalculationType
{
    use BaseEnum;

    case PERCENTAGE;
    case FIXED;
}
