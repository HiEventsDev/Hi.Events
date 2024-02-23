<?php

namespace TicketKitten\DomainObjects\Status;

use TicketKitten\DomainObjects\Enums\BaseEnum;

enum TicketStatus
{
    use BaseEnum;

    case ACTIVE;
    case INACTIVE;
}
