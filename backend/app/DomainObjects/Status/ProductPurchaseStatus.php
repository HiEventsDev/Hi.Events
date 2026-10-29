<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum ProductPurchaseStatus
{
    use BaseEnum;

    case SOLD;
    case AWAITING_PAYMENT;
    case CANCELLED;
}
