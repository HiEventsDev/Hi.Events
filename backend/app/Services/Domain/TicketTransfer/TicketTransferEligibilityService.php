<?php

namespace HiEvents\Services\Domain\TicketTransfer;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\TicketTransferBlock;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\TicketTransferStatus;
use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Services\Domain\TicketTransfer\DTO\TicketTransferContextDTO;

class TicketTransferEligibilityService
{
    public const MAX_STARTS_PER_DAY = 5;

    public const MAX_PENDING_HOURS = 72;

    public function blockOnStarting(TicketTransferContextDTO $context): ?TicketTransferBlock
    {
        if ($context->pendingTransfer() !== null) {
            return TicketTransferBlock::TRANSFER_PENDING;
        }

        return $this->blockOnHolderChange($context)
            ?? ($this->startsInLastDay($context) >= self::MAX_STARTS_PER_DAY ? TicketTransferBlock::TOO_MANY_ATTEMPTS : null);
    }

    public function blockOnHolderChange(TicketTransferContextDTO $context): ?TicketTransferBlock
    {
        $settings = $context->event->getEventSettings();

        return match (true) {
            ! $settings?->getAllowTicketTransfer() => TicketTransferBlock::TRANSFERS_DISABLED,
            ! $context->product->getIsTransferable() => TicketTransferBlock::TICKET_TYPE_NOT_TRANSFERABLE,
            $context->event->getStatus() !== EventStatus::LIVE->name => TicketTransferBlock::EVENT_NOT_LIVE,
            ! $context->order->isOrderCompleted() => TicketTransferBlock::ORDER_NOT_COMPLETED,
            $context->attendee->getStatus() !== AttendeeStatus::ACTIVE->name => TicketTransferBlock::TICKET_NOT_ACTIVE,
            $context->is_checked_in => TicketTransferBlock::CHECKED_IN,
            $context->has_open_listing => TicketTransferBlock::LISTED_FOR_RESALE,
            $context->occurrence->isCancelled() => TicketTransferBlock::DATE_CANCELLED,
            ! $context->occurrence->isFuture() => TicketTransferBlock::DATE_PASSED,
            $this->cutoff($context)->isPast() => TicketTransferBlock::CUTOFF_PASSED,
            $this->hasReachedLimit($context) => TicketTransferBlock::LIMIT_REACHED,
            default => null,
        };
    }

    public function cutoff(TicketTransferContextDTO $context): Carbon
    {
        return Carbon::parse($context->occurrence->getStartDate())
            ->subMinutes($context->event->getEventSettings()?->getTicketTransferCutoffMinutes() ?? 0);
    }

    public function expiryForNewTransfer(TicketTransferContextDTO $context): Carbon
    {
        return Carbon::now()->addHours(self::MAX_PENDING_HOURS)->min($this->cutoff($context));
    }

    private function hasReachedLimit(TicketTransferContextDTO $context): bool
    {
        $limit = $context->event->getEventSettings()?->getMaxTransfersPerTicket();

        return $limit !== null && $context->transfers
            ->filter(fn (TicketTransferDomainObject $transfer) => $transfer->getStatus() === TicketTransferStatus::ACCEPTED->name)
            ->count() >= $limit;
    }

    private function startsInLastDay(TicketTransferContextDTO $context): int
    {
        $since = Carbon::now()->subDay();

        return $context->transfers
            ->filter(fn (TicketTransferDomainObject $transfer) => Carbon::parse($transfer->getCreatedAt())->isAfter($since))
            ->count();
    }
}
