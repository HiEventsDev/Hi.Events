<?php

namespace HiEvents\DomainObjects\Enums;

enum BadgeSize: string
{
    use BaseEnum;

    case A6_PORTRAIT = 'A6_PORTRAIT';
    case A7_PORTRAIT = 'A7_PORTRAIT';
    case IN_4X3_LANDSCAPE = 'IN_4X3_LANDSCAPE';
    case IN_3X4_PORTRAIT = 'IN_3X4_PORTRAIT';
    case IN_4X6_PORTRAIT = 'IN_4X6_PORTRAIT';
    case CR80_PORTRAIT = 'CR80_PORTRAIT';
    case CR80_LANDSCAPE = 'CR80_LANDSCAPE';
    case LABEL_62X100 = 'LABEL_62X100';
    case CUSTOM = 'CUSTOM';
}
