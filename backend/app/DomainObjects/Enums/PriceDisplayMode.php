<?php

namespace HiEvents\DomainObjects\Enums;

enum PriceDisplayMode
{
    use BaseEnum;

    case INCLUSIVE;
    case EXCLUSIVE;
    case TAX_INCLUSIVE;
}
