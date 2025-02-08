<?php

namespace HiEvents\Http\Actions\Organizers;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Organizer\OrganizerResourcePublic;
use HiEvents\Services\Application\Handlers\Organizer\GetPublicOrganizerHandler;
use Illuminate\Http\JsonResponse;

class GetPublicOrganizerAction extends BaseAction
{
    public function __construct(
        private readonly GetPublicOrganizerHandler $handler,
    )
    {
    }

    public function __invoke(int $organizerId): JsonResponse
    {
        return $this->resourceResponse(
            resource: OrganizerResourcePublic::class,
            data: $this->handler->handle($organizerId),
        );
    }
}
