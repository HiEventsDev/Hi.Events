<?php

namespace HiEvents\DomainObjects\Enums;

enum MessagingEligibilityFailureEnum: string
{
    case STRIPE_NOT_CONNECTED = 'stripe_not_connected';
    case NO_PAID_ORDERS = 'no_paid_orders';
    case EVENT_TOO_NEW = 'event_too_new';
}
