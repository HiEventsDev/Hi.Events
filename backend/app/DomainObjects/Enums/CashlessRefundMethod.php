<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects\Enums;

enum CashlessRefundMethod: string
{
    use BaseEnum;

    case ORIGINAL_PAYMENT = 'ORIGINAL_PAYMENT';
    case CASH = 'CASH';
}
