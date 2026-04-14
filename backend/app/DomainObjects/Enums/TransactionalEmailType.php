<?php

namespace HiEvents\DomainObjects\Enums;

enum TransactionalEmailType: string
{
    case ORDER_SUMMARY = 'order_summary';
    case ORDER_FAILED = 'order_failed';
    case ATTENDEE_TICKET = 'attendee_ticket';
    case WAITLIST_OFFER = 'waitlist_offer';
    case WAITLIST_CONFIRMATION = 'waitlist_confirmation';
    case WAITLIST_OFFER_EXPIRED = 'waitlist_offer_expired';
}
