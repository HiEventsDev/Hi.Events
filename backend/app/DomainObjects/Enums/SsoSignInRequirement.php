<?php

namespace HiEvents\DomainObjects\Enums;

enum SsoSignInRequirement: string
{
    use BaseEnum;

    case SSO = 'SSO';
    case EXEMPT = 'EXEMPT';
    case PASSWORD = 'PASSWORD';
}
