<?php

namespace HiEvents\Listeners\Waitlist;

use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Domain\Waitlist\CancelWaitlistEntryService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CancelWaitlistEntriesOnOccurrenceCancelledListener implements ShouldQueue
{
    public function __construct(
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
        private readonly CancelWaitlistEntryService $cancelWaitlistEntryService,
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
                'status' => WaitlistEntryStatus::WAITING->name,
            ],
        );

        $offeredEntries = $this->waitlistEntryRepository->findWhere([
            'event_id' => $event->eventId,
            'event_occurrence_id' => $event->occurrenceId,
            'status' => WaitlistEntryStatus::OFFERED->name,
        ]);

        $offeredEntries->each(fn (WaitlistEntryDomainObject $entry) => $this->cancelWaitlistEntryService->cancelEntry($entry));
    }
}
