<?php

namespace HiEvents\Http\Actions\CheckInLists;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\CheckInList\DeleteCheckInListHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteCheckInListAction extends BaseAction
{
    public function __construct(
        private readonly DeleteCheckInListHandler $deleteCheckInListHandler,
    ) {}

    public function __invoke(int $eventId, int $checkInListId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->deleteCheckInListHandler->handle(
                eventId: $eventId,
                checkInListId: $checkInListId,
            );
        } catch (ResourceConflictException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_CONFLICT,
            );
        }

        return $this->noContentResponse();
    }
}
