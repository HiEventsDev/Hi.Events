<?php

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\CheckInList\CreateAttendeeCheckInPublicRequest;
use HiEvents\Resources\CheckInList\AttendeeCheckInPublicResource;
use HiEvents\Services\Handlers\CheckInList\Public\CreateAttendeeCheckInPublicHandler;
use HiEvents\Services\Handlers\CheckInList\Public\DTO\CreateAttendeeCheckInPublicDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CreateAttendeeCheckInPublicAction extends BaseAction
{
    public function __construct(
        private readonly CreateAttendeeCheckInPublicHandler $createAttendeeCheckInPublicHandler,
    )
    {
    }

    public function __invoke(
        string                             $checkInListUuid,
        CreateAttendeeCheckInPublicRequest $request,
    ): JsonResponse
    {
        try {
            $checkIns = $this->createAttendeeCheckInPublicHandler->handle(new CreateAttendeeCheckInPublicDTO(
                checkInListUuid: $checkInListUuid,
                checkInUserIpAddress: $request->ip(),
                attendeePublicIds: $request->validated('attendee_public_ids'),
            ));
        } catch (CannotCheckInException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_CONFLICT,
            );
        }

        return $this->resourceResponse(
            resource: AttendeeCheckInPublicResource::class,
            data: $checkIns->attendeeCheckIns,
            errors: $checkIns->errors->toArray()
        );
    }
}
