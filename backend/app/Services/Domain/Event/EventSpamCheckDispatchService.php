<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\Jobs\Event\EventSpamCheckJob;

class EventSpamCheckDispatchService
{
    public function __construct(
        private readonly EventSpamCheckService $eventSpamCheckService,
    ) {}

    public function dispatchForEvent(int $eventId): void
    {
        if (! $this->eventSpamCheckService->isEnabled()) {
            return;
        }

        EventSpamCheckJob::dispatch($eventId)->afterCommit();
    }
}
