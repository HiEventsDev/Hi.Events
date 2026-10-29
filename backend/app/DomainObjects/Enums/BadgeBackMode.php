<?php

namespace HiEvents\DomainObjects\Enums;

enum BadgeBackMode: string
{
    use BaseEnum;

    case NONE = 'NONE';
    case SAME = 'SAME';
    case INFO = 'INFO';
}
