<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum AccountSsoConnectionStatus: string
{
    use BaseEnum;

    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
