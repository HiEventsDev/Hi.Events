<?php

namespace HiEvents\DomainObjects\Enums;

enum EmailBounceType
{
    use BaseEnum;

    case PERMANENT;
    case TRANSIENT;
}
