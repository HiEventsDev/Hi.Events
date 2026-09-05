<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum EventSpamCheckStatus
{
    use BaseEnum;

    case FLAGGED;
    case CLEAN;
    case APPROVED;
    case CONFIRMED_SPAM;
}
