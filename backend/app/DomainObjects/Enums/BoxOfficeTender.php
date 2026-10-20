<?php

namespace HiEvents\DomainObjects\Enums;

enum BoxOfficeTender: string
{
    use BaseEnum;

    case CASH = 'CASH';
    case CARD = 'CARD';
    case COMP = 'COMP';
    case OTHER = 'OTHER';
    case FREE = 'FREE';
}
