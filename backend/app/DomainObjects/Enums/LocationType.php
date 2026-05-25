<?php

namespace HiEvents\DomainObjects\Enums;

enum LocationType
{
    use BaseEnum;

    case IN_PERSON;
    case ONLINE;
}
