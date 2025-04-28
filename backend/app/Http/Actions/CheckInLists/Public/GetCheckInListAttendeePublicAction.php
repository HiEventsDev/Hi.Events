<?php

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Attendee\AttendeeWithCheckInPublicResource;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListAttendeePublicHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GetCheckInListAttendeePublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListAttendeePublicHandler $getCheckInListAttendeePublicHandler,
    )
    {
    }

    public function __invoke(string $shortId, string $attendeePublicId, Request $request): JsonResponse
    {
        try {
            $attendee = $this->getCheckInListAttendeePublicHandler->handle(
                shortId: $shortId,
                attendeePublicId: $attendeePublicId,
            );
        } catch (CannotCheckInException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_FORBIDDEN,
            );
        }

        return $this->resourceResponse(
            resource: AttendeeWithCheckInPublicResource::class,
            data: $attendee,
        );
    }
}
