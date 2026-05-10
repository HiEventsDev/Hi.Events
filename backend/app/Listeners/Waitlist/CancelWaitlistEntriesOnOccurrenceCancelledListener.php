<?php

namespace HiEvents\Listeners\Waitlist;

use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * When an occurrence is cancelled, bulk-cancel any WAITING or OFFERED waitlist
 * entries scoped to that occurrence. Without this, the capacity-released event
 * fired by attendee cancellation would let those entries pass the per-entry
 * capacity check (capacity goes UP when attendees are cancelled) and trigger
 * offer emails for an occurrence that no longer exists.
 */
class CancelWaitlistEntriesOnOccurrenceCancelledListener implements ShouldQueue
{
    public function __construct(
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
    ) {}

    public function handle(OccurrenceCancelledEvent $event): void
    {
        $this->waitlistEntryRepository->updateWhere(
            attributes: [
                'status' => WaitlistEntryStatus::CANCELLED->name,
                'cancelled_at' => now(),
            ],
            where: [
                'event_id' => $event->eventId,
                'event_occurrence_id' => $event->occurrenceId,
                ['status', 'in', [
                    WaitlistEntryStatus::WAITING->name,
                    WaitlistEntryStatus::OFFERED->name,
                ]],
            ],
        );
    }
}
