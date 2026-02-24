<?php

namespace HiEvents\DomainObjects\Enums;

enum HomepageBackgroundType: string
{
    use BaseEnum;

    case MIRROR_COVER_IMAGE = 'MIRROR_COVER_IMAGE';
    case COLOR = 'COLOR';
    case COLOR_NEW = 'color';
    case GRADIENT = 'gradient';
    case IMAGE = 'image';
}
