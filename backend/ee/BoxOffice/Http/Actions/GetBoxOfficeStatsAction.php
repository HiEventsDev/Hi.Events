<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\BoxOffice\Http\Request\GetBoxOfficeStatsRequest;
use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeStatsResource;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\GetBoxOfficeStatsDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\GetBoxOfficeStatsHandler;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class GetBoxOfficeStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetBoxOfficeStatsHandler $getBoxOfficeStatsHandler,
    ) {}

    public function __invoke(GetBoxOfficeStatsRequest $request, int $eventId, int $boxOfficeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: BoxOfficeStatsResource::class,
            data: $this->getBoxOfficeStatsHandler->handle(
                new GetBoxOfficeStatsDTO(
                    event_id: $eventId,
                    box_office_id: $boxOfficeId,
                    from: $request->validated('from'),
                    to: $request->validated('to'),
                ),
            ),
        );
    }
}
