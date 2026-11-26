<?php

namespace HiEvents\DomainObjects\Enums;

enum TicketResaleNotice: string
{
    use BaseEnum;

    case LISTED_FOR_SELLER = 'LISTED_FOR_SELLER';
    case SOLD_FOR_SELLER = 'SOLD_FOR_SELLER';
    case UNSOLD_FOR_SELLER = 'UNSOLD_FOR_SELLER';
    case CLOSED_FOR_SELLER = 'CLOSED_FOR_SELLER';
    case REFUND_FAILED_FOR_SELLER = 'REFUND_FAILED_FOR_SELLER';
    case REFUND_FAILED_FOR_ORGANIZER = 'REFUND_FAILED_FOR_ORGANIZER';
    case SOLD_WITHOUT_REFUND_FOR_SELLER = 'SOLD_WITHOUT_REFUND_FOR_SELLER';
    case LISTED_FOR_HOLDER = 'LISTED_FOR_HOLDER';
    case SOLD_FOR_HOLDER = 'SOLD_FOR_HOLDER';
    case RETURNED_FOR_HOLDER = 'RETURNED_FOR_HOLDER';

    public function subject(): string
    {
        return match ($this) {
            self::LISTED_FOR_SELLER => __('Your ticket is listed for resale'),
            self::SOLD_FOR_SELLER => __('Your ticket sold and your refund is on its way'),
            self::UNSOLD_FOR_SELLER => __('Your ticket did not sell and is yours to use'),
            self::CLOSED_FOR_SELLER => __('Your ticket resale listing was closed'),
            self::REFUND_FAILED_FOR_SELLER => __('There was a problem refunding your resold ticket'),
            self::REFUND_FAILED_FOR_ORGANIZER => __('A ticket resale refund failed'),
            self::SOLD_WITHOUT_REFUND_FOR_SELLER => __('Your ticket sold'),
            self::LISTED_FOR_HOLDER => __('Your ticket has been listed for resale'),
            self::SOLD_FOR_HOLDER => __('Your ticket was resold and no longer works'),
            self::RETURNED_FOR_HOLDER => __('Your ticket is no longer listed for resale'),
        };
    }

    public function summary(string $eventTitle, string $ticketTitle, ?string $amount): string
    {
        $replacements = ['event' => $eventTitle, 'ticket' => $ticketTitle, 'amount' => $amount ?? ''];

        return match ($this) {
            self::LISTED_FOR_SELLER => __('Your **:ticket** ticket for **:event** is listed for resale. While it is listed it cannot be used. You can withdraw the listing from your order page. If it sells, you will get about **:amount** back.', $replacements),
            self::SOLD_FOR_SELLER => __('Your **:ticket** ticket for **:event** sold. **:amount** is on its way back to the card you paid with, and it can take 5 to 10 days to appear. The ticket no longer works.', $replacements),
            self::UNSOLD_FOR_SELLER => __('Your **:ticket** ticket for **:event** did not sell before resale closed. It is yours to use and works as normal.', $replacements),
            self::CLOSED_FOR_SELLER => __('The event organizer closed the resale listing for your **:ticket** ticket for **:event**. The ticket was not sold. Check your order page or contact the event organizer.', $replacements),
            self::REFUND_FAILED_FOR_SELLER => __('Your **:ticket** ticket for **:event** sold, but we could not complete your refund of **:amount**. The event organizer has been told. Please contact them about your refund.', $replacements),
            self::REFUND_FAILED_FOR_ORGANIZER => __('A resale refund of **:amount** to the seller of a **:ticket** ticket for **:event** failed. Retry it from the event\'s resale page, or refund the seller another way.', $replacements),
            self::SOLD_WITHOUT_REFUND_FOR_SELLER => __('Your **:ticket** ticket for **:event** sold and no longer works. No refund is due, because your order had already been refunded or the fees of the new sale used up its value.', $replacements),
            self::LISTED_FOR_HOLDER => __('The person who bought your **:ticket** ticket for **:event** has listed it for resale. While it is listed it cannot be used. If you still plan to go, contact the person who bought it.', $replacements),
            self::SOLD_FOR_HOLDER => __('The person who bought your **:ticket** ticket for **:event** resold it. The ticket no longer works. Contact the person who bought it if you have questions.', $replacements),
            self::RETURNED_FOR_HOLDER => __('Your **:ticket** ticket for **:event** is no longer listed for resale. It works as normal again.', $replacements),
        };
    }

    public function isForOrganizer(): bool
    {
        return $this === self::REFUND_FAILED_FOR_ORGANIZER;
    }
}
