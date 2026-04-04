<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum EventOccurrenceStatus
{
    use BaseEnum;

    case ACTIVE;
    case CANCELLED;
    case SOLD_OUT;
}
