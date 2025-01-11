<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum InvoiceStatus
{
    use BaseEnum;

    case UNPAID;
    case PAID;
    case VOID;
}
