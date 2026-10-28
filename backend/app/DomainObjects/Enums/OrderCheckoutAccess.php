<?php

namespace HiEvents\DomainObjects\Enums;

enum OrderCheckoutAccess
{
    case OWNER;
    case PAYMENT_LINK;
    case NONE;

    public function canPay(): bool
    {
        return $this !== self::NONE;
    }
}
