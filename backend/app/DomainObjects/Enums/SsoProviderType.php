<?php

namespace HiEvents\DomainObjects\Enums;

enum SsoProviderType: string
{
    use BaseEnum;

    case GOOGLE = 'GOOGLE';
    case MICROSOFT = 'MICROSOFT';
    case OIDC = 'OIDC';
}
