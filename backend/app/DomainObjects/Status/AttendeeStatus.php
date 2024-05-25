<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum AttendeeStatus
{
    use BaseEnum;

    case ACTIVE;
    case CANCELLED;
}
