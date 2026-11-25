<?php

namespace HiEvents\Services\Domain\TicketResale;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\TicketResaleBlock;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleContextDTO;

class TicketResaleEligibilityService
{
    private const RESELLABLE_PRICE_TYPES = [
        ProductPriceType::PAID,
        ProductPriceType::TIERED,
    ];

    public function blockOnListing(TicketResaleContextDTO $context): ?TicketResaleBlock
    {
        $settings = $context->event->getEventSettings();

        return match (true) {
            $context->has_open_listing => TicketResaleBlock::ALREADY_LISTED,
            $context->has_pending_transfer => TicketResaleBlock::TRANSFER_PENDING,
            ! $settings?->getAllowTicketResale() => TicketResaleBlock::RESALE_DISABLED,
            ! $context->product->getIsResellable() => TicketResaleBlock::TICKET_TYPE_NOT_RESELLABLE,
            $context->event->getStatus() !== EventStatus::LIVE->name => TicketResaleBlock::EVENT_NOT_LIVE,
            ! $this->hasResellablePriceType($context) => TicketResaleBlock::PRICE_TYPE_NOT_SUPPORTED,
            ! $context->is_price_on_sale => TicketResaleBlock::TICKET_TYPE_NOT_ON_SALE,
            $context->is_seated => TicketResaleBlock::SEATED,
            ! $context->order->isOrderCompleted() => TicketResaleBlock::ORDER_NOT_COMPLETED,
            $context->attendee->getStatus() !== AttendeeStatus::ACTIVE->name => TicketResaleBlock::TICKET_NOT_ACTIVE,
            $context->is_checked_in => TicketResaleBlock::CHECKED_IN,
            $context->attendee->getTransferredAt() !== null => TicketResaleBlock::TRANSFERRED,
            $context->occurrence->isCancelled() => TicketResaleBlock::DATE_CANCELLED,
            ! $context->occurrence->isFuture() => TicketResaleBlock::DATE_PASSED,
            $this->isPastCutoff($context) => TicketResaleBlock::CUTOFF_PASSED,
            ! $context->paid_online_through_stripe => TicketResaleBlock::NOT_PAID_ONLINE,
            ! $context->paid_on_current_stripe_account => TicketResaleBlock::STRIPE_ACCOUNT_CHANGED,
            ! $context->has_only_resale_refunds => TicketResaleBlock::ORDER_REFUNDED,
            $context->seller_unit_gross <= 0 || $context->current_unit_gross <= 0 => TicketResaleBlock::NOTHING_TO_REFUND,
            ! $context->payment_fees_recorded => TicketResaleBlock::PAYMENT_NOT_CONFIRMED,
            ! $context->settles_in_order_currency => TicketResaleBlock::CURRENCY_NOT_SUPPORTED,
            default => null,
        };
    }

    public function blockOnOfferingResale(TicketResaleContextDTO $context): ?TicketResaleBlock
    {
        $block = $this->blockOnListing($context);

        return $block === TicketResaleBlock::PAYMENT_NOT_CONFIRMED ? null : $block;
    }

    public function cutoff(TicketResaleContextDTO $context): Carbon
    {
        return Carbon::parse($context->occurrence->getStartDate())
            ->subMinutes($context->event->getEventSettings()?->getTicketResaleCutoffMinutes() ?? 0);
    }

    public function isPastCutoff(TicketResaleContextDTO $context): bool
    {
        return $this->cutoff($context)->isPast();
    }

    private function hasResellablePriceType(TicketResaleContextDTO $context): bool
    {
        return in_array(ProductPriceType::fromName($context->product->getType()), self::RESELLABLE_PRICE_TYPES, true);
    }
}
