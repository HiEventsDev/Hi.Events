<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum CashlessTopupStatus: string
{
    use BaseEnum;

    case PENDING = 'PENDING';
    case CREDITED = 'CREDITED';
    case CANCELLED = 'CANCELLED';
}
