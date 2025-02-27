<?php

namespace HiEvents\DomainObjects\Status;

enum OrderStatus
{
    case RESERVED;
    case CANCELLED;
    case COMPLETED;
    case AWAITING_OFFLINE_PAYMENT;

    public static function getHumanReadableStatus(string $status): string
    {
        return match ($status) {
            self::RESERVED->name => __('Reserved'),
            self::CANCELLED->name => __('Cancelled'),
            self::COMPLETED->name => __('Completed'),
            self::AWAITING_OFFLINE_PAYMENT->name => __('Awaiting offline payment'),
        };
    }
}

