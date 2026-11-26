<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum CrmPendingSubscriptionStatus: string
{
    use BaseEnum;

    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case SUPERSEDED = 'SUPERSEDED';
}
