<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeResource;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\GetBoxOfficeHandler;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetBoxOfficeAction extends BaseAction
{
    public function __construct(
        private readonly GetBoxOfficeHandler $getBoxOfficeHandler,
    ) {}

    public function __invoke(int $eventId, int $boxOfficeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: BoxOfficeResource::class,
            data: $this->getBoxOfficeHandler->handle(
                boxOfficeId: $boxOfficeId,
                eventId: $eventId,
            ),
        );
    }
}
