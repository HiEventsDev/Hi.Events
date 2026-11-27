<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmSegmentField: string
{
    use BaseEnum;

    case ORDERS_COUNT = 'orders_count';
    case TICKETS_COUNT = 'tickets_count';
    case EVENTS_COUNT = 'events_count';
    case ATTENDED_COUNT = 'attended_count';
    case NO_SHOW_COUNT = 'no_show_count';
    case TOTAL_SPENT = 'total_spent';
    case LAST_ORDER_AT = 'last_order_at';
    case LAST_ATTENDED_AT = 'last_attended_at';
    case FIRST_SEEN_AT = 'first_seen_at';
    case TICKET_EVENT = 'ticket_event';
    case CHECKED_IN_EVENT = 'checked_in_event';
    case TICKET_PRODUCT = 'ticket_product';
    case TICKET_LOCATION = 'ticket_location';
    case TAG = 'tag';
    case EMAIL_MARKETING_STATUS = 'email_marketing_status';
    case SMS_MARKETING_STATUS = 'sms_marketing_status';
    case HAS_PHONE = 'has_phone';
    case SOURCE = 'source';
    case USED_PROMO_CODE = 'used_promo_code';
    case WAITLIST_MISSED_OUT = 'waitlist_missed_out';
    case ABANDONED_CHECKOUT = 'abandoned_checkout';

    public function kind(): CrmSegmentFieldKind
    {
        return match ($this) {
            self::ORDERS_COUNT,
            self::TICKETS_COUNT,
            self::EVENTS_COUNT,
            self::ATTENDED_COUNT,
            self::NO_SHOW_COUNT => CrmSegmentFieldKind::NUMBER,
            self::TOTAL_SPENT => CrmSegmentFieldKind::MONEY,
            self::LAST_ORDER_AT,
            self::LAST_ATTENDED_AT,
            self::FIRST_SEEN_AT => CrmSegmentFieldKind::DATE,
            self::TICKET_EVENT,
            self::CHECKED_IN_EVENT,
            self::TICKET_PRODUCT,
            self::TICKET_LOCATION,
            self::TAG => CrmSegmentFieldKind::ENTITY,
            self::EMAIL_MARKETING_STATUS,
            self::SMS_MARKETING_STATUS,
            self::SOURCE => CrmSegmentFieldKind::OPTION,
            self::USED_PROMO_CODE,
            self::WAITLIST_MISSED_OUT,
            self::ABANDONED_CHECKOUT,
            self::HAS_PHONE => CrmSegmentFieldKind::FLAG,
        };
    }

    public function isFinancial(): bool
    {
        return $this === self::TOTAL_SPENT;
    }
}
