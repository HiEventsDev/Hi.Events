<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum TicketResaleRefundStatus
{
    use BaseEnum;

    case PENDING;
    case SUCCEEDED;
    case FAILED;
}
