<?php

namespace TicketKitten\DomainObjects\Status;

use TicketKitten\DomainObjects\Enums\BaseEnum;

enum AttendeeStatus
{
    use BaseEnum;

    case ACTIVE;
    case CANCELLED;
}
