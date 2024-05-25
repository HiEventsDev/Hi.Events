<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum TicketStatus
{
    use BaseEnum;

    case ACTIVE;
    case INACTIVE;
}
