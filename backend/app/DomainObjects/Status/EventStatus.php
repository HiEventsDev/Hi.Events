<?php

namespace TicketKitten\DomainObjects\Status;

use TicketKitten\DomainObjects\Enums\BaseEnum;

enum EventStatus
{
    use BaseEnum;

    case DRAFT;
    case LIVE;
    case PAUSED;
}
