<?php

namespace HiEvents\Services\Application\Handlers\Event;

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
            duplicateProducts: $data->duplicateProducts,
            duplicateQuestions: $data->duplicateQuestions,
            duplicateSettings: $data->duplicateSettings,
            duplicatePromoCodes: $data->duplicatePromoCodes,
            duplicateCapacityAssignments: $data->duplicateCapacityAssignments,
            duplicateCheckInLists: $data->duplicateCheckInLists,
            duplicateEventCoverImage: $data->duplicateEventCoverImage,
            duplicateTicketLogo: $data->duplicateTicketLogo,
            duplicateWebhooks: $data->duplicateWebhooks,
            duplicateAffiliates: $data->duplicateAffiliates,
            description: $data->description,
            endDate: $data->endDate,
        );
    }
}
