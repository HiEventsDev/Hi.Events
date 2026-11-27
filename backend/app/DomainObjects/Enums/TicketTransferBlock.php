<?php

namespace HiEvents\DomainObjects\Enums;

enum TicketTransferBlock: string
{
    use BaseEnum;

    case TRANSFERS_DISABLED = 'TRANSFERS_DISABLED';
    case TICKET_TYPE_NOT_TRANSFERABLE = 'TICKET_TYPE_NOT_TRANSFERABLE';
    case EVENT_NOT_LIVE = 'EVENT_NOT_LIVE';
    case ORDER_NOT_COMPLETED = 'ORDER_NOT_COMPLETED';
    case TICKET_NOT_ACTIVE = 'TICKET_NOT_ACTIVE';
    case CHECKED_IN = 'CHECKED_IN';
    case DATE_PASSED = 'DATE_PASSED';
    case DATE_CANCELLED = 'DATE_CANCELLED';
    case CUTOFF_PASSED = 'CUTOFF_PASSED';
    case LIMIT_REACHED = 'LIMIT_REACHED';
    case TRANSFER_PENDING = 'TRANSFER_PENDING';
    case TOO_MANY_ATTEMPTS = 'TOO_MANY_ATTEMPTS';
    case LISTED_FOR_RESALE = 'LISTED_FOR_RESALE';

    public function message(): string
    {
        return match ($this) {
            self::TRANSFERS_DISABLED => __('Ticket transfers are not available for this event.'),
            self::TICKET_TYPE_NOT_TRANSFERABLE => __('This type of ticket cannot be transferred.'),
            self::EVENT_NOT_LIVE => __('Tickets for this event cannot be transferred right now.'),
            self::ORDER_NOT_COMPLETED => __('This ticket cannot be transferred until its order is complete.'),
            self::TICKET_NOT_ACTIVE => __('This ticket is no longer valid, so it cannot be transferred.'),
            self::CHECKED_IN => __('This ticket has already been checked in, so it cannot be transferred.'),
            self::DATE_PASSED => __('This event date has passed.'),
            self::DATE_CANCELLED => __('This event date was cancelled.'),
            self::CUTOFF_PASSED => __('Transfers have closed for this event.'),
            self::LIMIT_REACHED => __('This ticket has been transferred as many times as the organizer allows.'),
            self::TRANSFER_PENDING => __('This ticket already has a transfer waiting to be accepted.'),
            self::TOO_MANY_ATTEMPTS => __('Too many transfers have been started for this ticket today. Try again tomorrow.'),
            self::LISTED_FOR_RESALE => __('This ticket is listed for resale. Withdraw the listing before transferring it.'),
        };
    }
}
