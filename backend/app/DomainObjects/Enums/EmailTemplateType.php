<?php

namespace HiEvents\DomainObjects\Enums;

enum EmailTemplateType: string
{
    use BaseEnum;

    case ORDER_CONFIRMATION = 'order_confirmation';
    case ATTENDEE_TICKET = 'attendee_ticket';
    case OCCURRENCE_CANCELLATION = 'occurrence_cancellation';
    case CASHLESS_TOPUP = 'cashless_topup';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_CONFIRMATION => __('Order Confirmation'),
            self::ATTENDEE_TICKET => __('Attendee Ticket'),
            self::OCCURRENCE_CANCELLATION => __('Date Cancellation'),
            self::CASHLESS_TOPUP => __('Cashless Top-up'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ORDER_CONFIRMATION => __('Sent to the customer after placing an order'),
            self::ATTENDEE_TICKET => __('Sent to each attendee with their ticket'),
            self::OCCURRENCE_CANCELLATION => __('Sent to attendees when a scheduled date is cancelled'),
            self::CASHLESS_TOPUP => __('Sent to attendees when their cashless balance is topped up online'),
        };
    }

    public function ctaUrlToken(): string
    {
        return match ($this) {
            self::ORDER_CONFIRMATION => 'order.url',
            self::ATTENDEE_TICKET => 'ticket.url',
            self::OCCURRENCE_CANCELLATION => 'event.url',
            self::CASHLESS_TOPUP => 'cashless.url',
        };
    }
}
