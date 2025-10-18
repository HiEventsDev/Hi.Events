<?php

namespace HiEvents\DomainObjects\Enums;

enum PaymentProviders: string
{
    use BaseEnum;

    case STRIPE = 'STRIPE';
    case GRUBCHAIN = 'GRUBCHAIN';
    case OFFLINE = 'OFFLINE';
}
