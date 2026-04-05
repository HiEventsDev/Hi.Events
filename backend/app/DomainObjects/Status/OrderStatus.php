<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum OrderStatus
{
    use BaseEnum;

    case RESERVED;
    case CANCELLED;
    case COMPLETED;
    case AWAITING_OFFLINE_PAYMENT;
    case AWAITING_APPROVAL;
    case ABANDONED;
    case PROVISIONAL;

    public static function getHumanReadableStatus(string $status): string
    {
        return match ($status) {
            self::RESERVED->name => __('Reserved'),
            self::CANCELLED->name => __('Cancelled'),
            self::COMPLETED->name => __('Completed'),
            self::AWAITING_OFFLINE_PAYMENT->name => __('Awaiting offline payment'),
            self::AWAITING_APPROVAL->name => __('Awaiting approval'),
            self::ABANDONED->name => __('Abandoned'),
            self::PROVISIONAL->name => __('Provisional'),
        };
    }
}

