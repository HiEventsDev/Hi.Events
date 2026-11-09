<?php

namespace HiEvents\DomainObjects\Enums;

enum BadgeSheet: string
{
    use BaseEnum;

    case A4 = 'A4';
    case LETTER = 'LETTER';
    case NONE = 'NONE';
}
