<?php

namespace HiEvents\DomainObjects\Enums;

enum AttendeeCheckInActionType: string
{
    use BaseEnum;

    case CHECK_IN = 'check-in';
    case CHECK_IN_AND_MARK_ORDER_AS_PAID = 'check-in-and-mark-order-as-paid';
}
