<?php

namespace HiEvents\DomainObjects\Enums;

enum BadgeTemplate: string
{
    use BaseEnum;

    case CLASSIC = 'CLASSIC';
    case BANNER = 'BANNER';
    case MINIMAL = 'MINIMAL';
    case BOLD = 'BOLD';
    case SIDEBAR = 'SIDEBAR';
    case ARTWORK = 'ARTWORK';
}
