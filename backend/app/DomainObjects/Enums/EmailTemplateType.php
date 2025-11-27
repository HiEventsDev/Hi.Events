<?php

namespace HiEvents\DomainObjects\Enums;

enum EmailTemplateType: string
{
    use BaseEnum;

    case ORDER_CONFIRMATION = 'order_confirmation';
    case ATTENDEE_TICKET = 'attendee_ticket';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_CONFIRMATION => __('Order Confirmation'),
            self::ATTENDEE_TICKET => __('Attendee Ticket'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ORDER_CONFIRMATION => __('Sent to the customer after placing an order'),
            self::ATTENDEE_TICKET => __('Sent to each attendee with their ticket'),
        };
    }
}