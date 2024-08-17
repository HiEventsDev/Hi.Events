<?php

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Resources\Attendee\AttendeeWithCheckInPublicResource;
use HiEvents\Services\Handlers\CheckInList\Public\GetCheckInListAttendeesPublicHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GetCheckInListAttendeesPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListAttendeesPublicHandler $getCheckInListAttendeesPublicHandler,
    )
    {
    }

    public function __invoke(string $uuid, Request $request): JsonResponse
    {
        try {
            $attendees = $this->getCheckInListAttendeesPublicHandler->handle(
                shortId: $uuid,
                queryParams: QueryParamsDTO::fromArray($request->query->all())
            );
        } catch (CannotCheckInException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_FORBIDDEN,
            );
        }

        return $this->resourceResponse(
            resource: AttendeeWithCheckInPublicResource::class,
            data: $attendees,
        );
    }
}
