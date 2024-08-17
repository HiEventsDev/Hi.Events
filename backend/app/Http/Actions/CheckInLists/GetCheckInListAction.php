<?php

namespace HiEvents\Http\Actions\CheckInLists;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\CheckInList\CheckInListResource;
use HiEvents\Services\Handlers\CheckInList\GetCheckInListHandler;
use Illuminate\Http\JsonResponse;

class GetCheckInListAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListHandler $getCheckInListHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $checkInListId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $checkInList = $this->getCheckInListHandler->handle(
            checkInListId: $checkInListId,
            eventId: $eventId,
        );

        return $this->resourceResponse(
            resource: CheckInListResource::class,
            data: $checkInList,
        );
    }
}
