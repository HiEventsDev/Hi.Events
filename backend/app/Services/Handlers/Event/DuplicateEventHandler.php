<?php

namespace HiEvents\Services\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Services\Domain\Event\DTO\DuplicateEventDataDTO;
use HiEvents\Services\Domain\Event\DuplicateEventService;
use Throwable;

class DuplicateEventHandler
{
    public function __construct(
        private readonly DuplicateEventService $duplicateEventService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(DuplicateEventDataDTO $data): EventDomainObject
    {
        return $this->duplicateEventService->duplicateEvent(
            eventId: $data->eventId,
            accountId: $data->accountId,
            title: $data->title,
            startDate: $data->startDate,
            duplicateTickets: $data->duplicateTickets,
            duplicateQuestions: $data->duplicateQuestions,
            duplicateSettings: $data->duplicateSettings,
            duplicatePromoCodes: $data->duplicatePromoCodes,
            description: $data->description,
            endDate: $data->endDate,
        );
    }
}
