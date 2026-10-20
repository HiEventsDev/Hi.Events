<?php

namespace HiEvents\DomainObjects\Enums;

enum AccountUserAccessScope: string
{
    use BaseEnum;

    case ACCOUNT = 'ACCOUNT';
    case RESTRICTED = 'RESTRICTED';
}
