<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum OrderApplicationFeeStatus: string
{
    use BaseEnum;

    case AWAITING_PAYMENT = 'AWAITING_PAYMENT';
    case PAID = 'PAID';
    case REFUNDED = 'REFUNDED';
    case PAYMENT_WAIVED = 'PAYMENT_WAIVED';
}
