<?php

namespace HiEvents\DomainObjects\Enums;

enum TeamAccessVia: string
{
    use BaseEnum;

    case ACCOUNT = 'ACCOUNT';
    case ORGANIZER = 'ORGANIZER';
    case EVENT = 'EVENT';
}
