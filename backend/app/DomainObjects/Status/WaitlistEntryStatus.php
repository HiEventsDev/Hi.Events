<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum WaitlistEntryStatus
{
    use BaseEnum;

    case WAITING;
    case OFFERED;
    case PURCHASED;
    case CANCELLED;
    case OFFER_EXPIRED;
}
