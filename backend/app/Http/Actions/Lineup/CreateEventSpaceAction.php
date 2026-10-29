<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Lineup\UpsertEventSpaceRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Lineup\EventSpaceResource;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertEventSpaceDTO;
use HiEvents\Services\Application\Handlers\Lineup\UpsertEventSpaceHandler;
use Illuminate\Http\JsonResponse;

class CreateEventSpaceAction extends BaseAction
{
    public function __construct(
        private readonly UpsertEventSpaceHandler $handler,
    ) {}

    public function __invoke(int $eventId, UpsertEventSpaceRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $space = $this->handler->handle(new UpsertEventSpaceDTO(
            event_id: $eventId,
            name: $request->validated('name'),
            event_location_id: $request->validated('event_location_id'),
        ));

        return $this->resourceResponse(
            resource: EventSpaceResource::class,
            data: $space,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
