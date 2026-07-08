<?php

namespace HiEvents\DomainObjects\Enums;

enum PaymentProviders: string
{
    use BaseEnum;

    case STRIPE = 'STRIPE';
    case RAZORPAY = 'RAZORPAY';
    case OFFLINE = 'OFFLINE';
}
