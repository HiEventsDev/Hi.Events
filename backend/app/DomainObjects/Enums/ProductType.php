<?php

namespace HiEvents\DomainObjects\Enums;

enum ProductType
{
    use BaseEnum;

    case TICKET;
    case GENERAL;
}
