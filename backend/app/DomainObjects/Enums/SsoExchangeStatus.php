<?php

namespace HiEvents\DomainObjects\Enums;

enum SsoExchangeStatus: string
{
    use BaseEnum;

    case AUTHENTICATED = 'AUTHENTICATED';
    case TWO_FACTOR_REQUIRED = 'TWO_FACTOR_REQUIRED';
    case CHOOSE_ACCOUNT = 'CHOOSE_ACCOUNT';
    case SIGNUP_REQUIRED = 'SIGNUP_REQUIRED';
    case LINKED = 'LINKED';
    case REAUTHENTICATED = 'REAUTHENTICATED';
    case TESTED = 'TESTED';
}
