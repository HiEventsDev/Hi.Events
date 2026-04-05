<?php

namespace HiEvents\DomainObjects\Enums;

enum EmailTemplateType: string
{
    use BaseEnum;

    case ORDER_CONFIRMATION = 'order_confirmation';
    case ATTENDEE_TICKET = 'attendee_ticket';
    case ORDER_FAILED = 'order_failed';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_CONFIRMATION => __('Order Confirmation'),
            self::ATTENDEE_TICKET => __('Attendee Ticket'),
            self::ORDER_FAILED => __('Order Failed'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ORDER_CONFIRMATION => __('Sent to the customer after placing an order'),
            self::ATTENDEE_TICKET => __('Sent to each attendee with their ticket'),
            self::ORDER_FAILED => __('Sent to the customer when their order is not successful'),
        };
    }
}