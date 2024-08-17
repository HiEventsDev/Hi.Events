<?php

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Resources\Attendee\AttendeeWithCheckInPublicResource;
use HiEvents\Services\Handlers\CheckInList\Public\GetCheckInListAttendeesPublicHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCheckInListAttendeesPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListAttendeesPublicHandler $getCheckInListAttendeesPublicHandler,
    )
    {
    }

    public function __invoke(string $uuid, Request $request): JsonResponse
    {
        $attendees = $this->getCheckInListAttendeesPublicHandler->handle(
            shortId: $uuid,
            queryParams: QueryParamsDTO::fromArray($request->query->all())
        );

        return $this->resourceResponse(
            resource: AttendeeWithCheckInPublicResource::class,
            data: $attendees,
        );
    }
}
