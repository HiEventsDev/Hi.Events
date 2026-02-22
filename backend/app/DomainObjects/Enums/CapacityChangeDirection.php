<?php

namespace HiEvents\DomainObjects\Enums;

enum CapacityChangeDirection
{
    use BaseEnum;

    case INCREASED;
    case DECREASED;
}
