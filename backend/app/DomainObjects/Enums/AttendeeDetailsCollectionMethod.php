<?php

namespace HiEvents\DomainObjects\Enums;

enum AttendeeDetailsCollectionMethod: string
{
    use BaseEnum;

    case PER_TICKET = 'PER_TICKET';
    case PER_ORDER = 'PER_ORDER';
}
