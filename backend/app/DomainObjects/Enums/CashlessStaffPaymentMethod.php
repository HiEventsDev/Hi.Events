<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects\Enums;

enum CashlessStaffPaymentMethod: string
{
    use BaseEnum;

    case CASH = 'CASH';
    case CARD_TERMINAL = 'CARD_TERMINAL';
    case OTHER = 'OTHER';
}
