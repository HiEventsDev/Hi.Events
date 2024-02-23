<?php

namespace TicketKitten\DomainObjects\Status;

use TicketKitten\DomainObjects\Enums\BaseEnum;

enum UserStatus
{
    use BaseEnum;

    case ACTIVE;
    case INVITED;
    case INACTIVE;
}
