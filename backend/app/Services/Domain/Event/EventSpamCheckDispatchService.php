<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
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
                [EventDomainObjectAbstract::STATUS, 'in', EventSpamCheckService::CHECKED_EVENT_STATUSES],
            ])
            ->each(fn (EventDomainObject $event) => EventSpamCheckJob::dispatch($event->getId())->afterCommit());
    }

    public function dispatchForEmailTemplate(EmailTemplateDomainObject $template): void
    {
        if ($template->getEventId() !== null) {
            $this->dispatchForEvent($template->getEventId());

            return;
        }

        if ($template->getOrganizerId() !== null) {
            $this->dispatchForOrganizer($template->getOrganizerId());
        }
    }
}
