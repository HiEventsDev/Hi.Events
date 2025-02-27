<?php

namespace HiEvents\DomainObjects\Enums;

enum StripeConnectAccountType: string
{
    use BaseEnum;

    case STANDARD = 'standard';
    case EXPRESS = 'express';
}
