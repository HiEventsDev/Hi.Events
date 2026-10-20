<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Lineup\EventLineupResource;
use HiEvents\Services\Application\Handlers\Lineup\GetEventLineupHandler;
use Illuminate\Http\JsonResponse;

class GetEventLineupAction extends BaseAction
{
    public function __construct(
        private readonly GetEventLineupHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: EventLineupResource::class,
            data: $this->handler->handle($eventId, forPublic: false),
        );
    }
}
