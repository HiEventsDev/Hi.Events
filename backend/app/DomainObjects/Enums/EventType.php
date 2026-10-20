<?php

namespace HiEvents\DomainObjects\Enums;

enum EventType
{
    use BaseEnum;

    case SINGLE;
    case RECURRING;
}
