<?php

namespace HiEvents\DomainObjects\Enums;

enum TicketResaleBlock: string
{
    use BaseEnum;

    case RESALE_DISABLED = 'RESALE_DISABLED';
    case TICKET_TYPE_NOT_RESELLABLE = 'TICKET_TYPE_NOT_RESELLABLE';
    case EVENT_NOT_LIVE = 'EVENT_NOT_LIVE';
    case PRICE_TYPE_NOT_SUPPORTED = 'PRICE_TYPE_NOT_SUPPORTED';
    case TICKET_TYPE_NOT_ON_SALE = 'TICKET_TYPE_NOT_ON_SALE';
    case SEATED = 'SEATED';
    case ORDER_NOT_COMPLETED = 'ORDER_NOT_COMPLETED';
    case TICKET_NOT_ACTIVE = 'TICKET_NOT_ACTIVE';
    case CHECKED_IN = 'CHECKED_IN';
    case TRANSFERRED = 'TRANSFERRED';
    case DATE_CANCELLED = 'DATE_CANCELLED';
    case DATE_PASSED = 'DATE_PASSED';
    case CUTOFF_PASSED = 'CUTOFF_PASSED';
    case NOT_PAID_ONLINE = 'NOT_PAID_ONLINE';
    case STRIPE_ACCOUNT_CHANGED = 'STRIPE_ACCOUNT_CHANGED';
    case PAYMENT_NOT_CONFIRMED = 'PAYMENT_NOT_CONFIRMED';
    case CURRENCY_NOT_SUPPORTED = 'CURRENCY_NOT_SUPPORTED';
    case ORDER_REFUNDED = 'ORDER_REFUNDED';
    case NOTHING_TO_REFUND = 'NOTHING_TO_REFUND';
    case TRANSFER_PENDING = 'TRANSFER_PENDING';
    case ALREADY_LISTED = 'ALREADY_LISTED';

    public function message(): string
    {
        return match ($this) {
            self::RESALE_DISABLED => __('Ticket resale is not available for this event.'),
            self::TICKET_TYPE_NOT_RESELLABLE => __('This type of ticket cannot be resold.'),
            self::EVENT_NOT_LIVE => __('Tickets for this event cannot be resold right now.'),
            self::PRICE_TYPE_NOT_SUPPORTED => __('This ticket is free or pay-what-you-want, so it cannot be resold.'),
            self::TICKET_TYPE_NOT_ON_SALE => __('This type of ticket is no longer on sale, so it cannot be resold.'),
            self::SEATED => __('This ticket has a reserved seat, so it cannot be resold.'),
            self::ORDER_NOT_COMPLETED => __('This ticket cannot be resold until its order is complete.'),
            self::TICKET_NOT_ACTIVE => __('This ticket is no longer valid, so it cannot be resold.'),
            self::CHECKED_IN => __('This ticket has already been checked in, so it cannot be resold.'),
            self::TRANSFERRED => __('This ticket was transferred, so it cannot be resold.'),
            self::DATE_CANCELLED => __('This event date was cancelled.'),
            self::DATE_PASSED => __('This event date has passed.'),
            self::CUTOFF_PASSED => __('Resale has closed for this event.'),
            self::NOT_PAID_ONLINE => __('This ticket was not paid for online, so it cannot be resold.'),
            self::STRIPE_ACCOUNT_CHANGED => __('This ticket cannot be resold because the organizer has changed how they take payments.'),
            self::PAYMENT_NOT_CONFIRMED => __('We are still confirming the payment for this ticket. Please try again in a few minutes.'),
            self::CURRENCY_NOT_SUPPORTED => __('This ticket cannot be resold because of the currency it was paid in.'),
            self::ORDER_REFUNDED => __('This ticket is on an order with a refund, so it cannot be resold.'),
            self::NOTHING_TO_REFUND => __('This ticket has nothing to refund, so it cannot be resold.'),
            self::TRANSFER_PENDING => __('This ticket has a transfer waiting to be accepted, so it cannot be resold.'),
            self::ALREADY_LISTED => __('This ticket is already listed for resale.'),
        };
    }
}
