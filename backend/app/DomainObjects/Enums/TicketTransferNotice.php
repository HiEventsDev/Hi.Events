<?php

namespace HiEvents\DomainObjects\Enums;

enum TicketTransferNotice: string
{
    use BaseEnum;

    case ACCEPTED_FOR_SENDER = 'ACCEPTED_FOR_SENDER';
    case ACCEPTED_FOR_BUYER = 'ACCEPTED_FOR_BUYER';
    case DECLINED_FOR_SENDER = 'DECLINED_FOR_SENDER';
    case CANCELLED_FOR_RECIPIENT = 'CANCELLED_FOR_RECIPIENT';

    public function subject(): string
    {
        return match ($this) {
            self::ACCEPTED_FOR_SENDER => __('Your ticket transfer is complete'),
            self::ACCEPTED_FOR_BUYER => __('A ticket from your order was transferred'),
            self::DECLINED_FOR_SENDER => __('Your ticket transfer was declined'),
            self::CANCELLED_FOR_RECIPIENT => __('A ticket transfer to you was cancelled'),
        };
    }

    public function summary(string $eventTitle, string $ticketTitle, string $maskedEmail): string
    {
        $replacements = ['event' => $eventTitle, 'ticket' => $ticketTitle, 'email' => $maskedEmail];

        return match ($this) {
            self::ACCEPTED_FOR_SENDER => __('**:email** accepted your **:ticket** ticket for **:event**. It is now theirs, and your copy of the ticket no longer works.', $replacements),
            self::ACCEPTED_FOR_BUYER => __('A **:ticket** ticket from your order for **:event** was transferred to **:email**. It no longer appears on your order.', $replacements),
            self::DECLINED_FOR_SENDER => __('**:email** declined your **:ticket** ticket for **:event**. The ticket is still yours and works as before.', $replacements),
            self::CANCELLED_FOR_RECIPIENT => __('The **:ticket** ticket for **:event** that **:email** offered you is no longer available, because they cancelled the transfer.', $replacements),
        };
    }

    public function asksToReportIfUnexpected(): bool
    {
        return $this === self::ACCEPTED_FOR_SENDER || $this === self::ACCEPTED_FOR_BUYER;
    }
}
