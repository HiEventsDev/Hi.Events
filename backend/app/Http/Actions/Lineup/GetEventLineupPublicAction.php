<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\Http\Actions\Events\BasePublicEventAction;
use HiEvents\Resources\Lineup\EventLineupResourcePublic;
use HiEvents\Services\Application\Handlers\Lineup\GetEventLineupHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GetEventLineupPublicAction extends BasePublicEventAction
{
    public function __construct(
        private readonly GetEventLineupHandler $handler,
    ) {}

    public function __invoke(int $eventId): Response|JsonResponse
    {
        if (! $this->canUserViewEventWithId($eventId)) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(
            resource: EventLineupResourcePublic::class,
            data: $this->handler->handle($eventId, forPublic: true),
        );
    }
}
