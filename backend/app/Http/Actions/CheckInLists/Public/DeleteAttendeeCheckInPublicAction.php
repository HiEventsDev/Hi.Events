<?php

namespace HiEvents\Http\Actions\CheckInLists\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Handlers\CheckInList\Public\DeleteAttendeeCheckInPublicHandler;
use HiEvents\Services\Handlers\CheckInList\Public\DTO\DeleteAttendeeCheckInPublicDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeleteAttendeeCheckInPublicAction extends BaseAction
{
    public function __construct(
        private readonly DeleteAttendeeCheckInPublicHandler $deleteAttendeeCheckInPublicHandler,
    )
    {
    }

    public function __invoke(
        string  $checkInListShortId,
        string  $checkInShortId,
        Request $request
    ): Response|JsonResponse
    {
        try {
            $this->deleteAttendeeCheckInPublicHandler->handle(new DeleteAttendeeCheckInPublicDTO(
                checkInListShortId: $checkInListShortId,
                checkInShortId: $checkInShortId,
                checkInUserIpAddress: $request->ip(),
            ));
        } catch (CannotCheckInException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_CONFLICT
            );
        }

        return $this->deletedResponse();
    }
}
