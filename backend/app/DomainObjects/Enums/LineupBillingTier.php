<?php

namespace HiEvents\DomainObjects\Enums;

enum LineupBillingTier: string
{
    use BaseEnum;

    case HEADLINER = 'HEADLINER';
    case FEATURED = 'FEATURED';
    case STANDARD = 'STANDARD';
}
