<?php

namespace HiEvents\DomainObjects\Enums;

enum HomepageLayout: string
{
    use BaseEnum;

    case MARQUEE = 'MARQUEE';
    case FLYER = 'FLYER';
    case STORY = 'STORY';
    case INVITE = 'INVITE';
}
