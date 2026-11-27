<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum CrmSmsMarketingStatus: string
{
    use BaseEnum;

    case NOT_SUBSCRIBED = 'NOT_SUBSCRIBED';
    case SUBSCRIBED = 'SUBSCRIBED';
    case UNSUBSCRIBED = 'UNSUBSCRIBED';
}
