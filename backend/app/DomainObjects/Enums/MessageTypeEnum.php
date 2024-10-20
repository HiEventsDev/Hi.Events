<?php

namespace HiEvents\DomainObjects\Enums;

enum MessageTypeEnum
{
    use BaseEnum;

    case ORDER;
    case PRODUCT;
    case ATTENDEE;
    case EVENT;
}
