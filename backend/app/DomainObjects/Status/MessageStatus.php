<?php

namespace TicketKitten\DomainObjects\Status;

use TicketKitten\DomainObjects\Enums\BaseEnum;

enum MessageStatus
{
    use BaseEnum;

    case PROCESSING;
    case SENT;
    case FAILED;
}
