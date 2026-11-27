<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum TicketResaleListingStatus
{
    use BaseEnum;

    case LISTED;
    case SOLD;
    case CLOSED;
}
