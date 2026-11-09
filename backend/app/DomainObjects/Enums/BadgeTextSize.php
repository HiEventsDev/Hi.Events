<?php

namespace HiEvents\DomainObjects\Enums;

enum BadgeTextSize: string
{
    use BaseEnum;

    case XL = 'XL';
    case L = 'L';
    case M = 'M';
    case S = 'S';
}
