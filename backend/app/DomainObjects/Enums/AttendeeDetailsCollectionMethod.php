<?php

namespace HiEvents\DomainObjects\Enums;

enum AttendeeDetailsCollectionMethod
{
    use BaseEnum;

    case PER_TICKET;
    case PER_ORDER;
}
