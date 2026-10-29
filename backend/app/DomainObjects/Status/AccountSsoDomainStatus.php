<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum AccountSsoDomainStatus: string
{
    use BaseEnum;

    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case FAILING = 'FAILING';
    case SUSPENDED = 'SUSPENDED';
}
