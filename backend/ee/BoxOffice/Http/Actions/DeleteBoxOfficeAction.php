<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DeleteBoxOfficeHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteBoxOfficeAction extends BaseAction
{
    public function __construct(
        private readonly DeleteBoxOfficeHandler $deleteBoxOfficeHandler,
    ) {}

    public function __invoke(int $eventId, int $boxOfficeId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->deleteBoxOfficeHandler->handle(
                eventId: $eventId,
                boxOfficeId: $boxOfficeId,
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
