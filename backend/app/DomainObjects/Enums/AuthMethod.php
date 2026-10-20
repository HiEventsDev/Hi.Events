<?php

namespace HiEvents\DomainObjects\Enums;

enum AuthMethod: string
{
    use BaseEnum;

    case PASSWORD = 'PASSWORD';
    case SSO = 'SSO';
    case IMPERSONATION = 'IMPERSONATION';
}
