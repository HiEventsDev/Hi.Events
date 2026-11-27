<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum TicketTransferStatus
{
    use BaseEnum;

    case PENDING;
    case ACCEPTED;
    case CANCELLED;
    case DECLINED;
    case EXPIRED;
}
