<?php

namespace HiEvents\DomainObjects\Enums;

enum EmailDeliveryProvider: string
{
    use BaseEnum;

    case POSTMARK = 'postmark';
}
