<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Jobs\Event\EventSpamCheckJob;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class EventSpamCheckDispatchService
{
    public function __construct(
        private readonly EventSpamCheckService $eventSpamCheckService,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function dispatchForEvent(int $eventId): void
    {
        if (! $this->eventSpamCheckService->isEnabled()) {
            return;
        }

        EventSpamCheckJob::dispatch($eventId)->afterCommit();
    }

    public function dispatchForOrganizer(int $organizerId): void
    {
        if (! $this->eventSpamCheckService->isEnabled()) {
            return;
        }

        $this->eventRepository
            ->findWhere([
                EventDomainObjectAbstract::ORGANIZER_ID => $organizerId,
                EventDomainObjectAbstract::STATUS => EventStatus::LIVE->name,
            ])
            ->each(fn (EventDomainObject $event) => EventSpamCheckJob::dispatch($event->getId())->afterCommit());
    }
}
