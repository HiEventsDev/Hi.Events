<?php

namespace HiEvents\DomainObjects\Enums;

enum AttributionSourceType: string
{
    use BaseEnum;

    case PAID = 'paid';
    case ORGANIC = 'organic';
    case REFERRAL = 'referral';
}
