<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Lineup\ProfileResource;
use HiEvents\Services\Application\Handlers\Lineup\GetProfilesHandler;
use Illuminate\Http\JsonResponse;

class GetProfilesAction extends BaseAction
{
    public function __construct(
        private readonly GetProfilesHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->isOrganizerReadAuthorized($organizerId);

        return $this->resourceResponse(
            resource: ProfileResource::class,
            data: $this->handler->handle($organizerId, $this->getAuthenticatedAccountId(), $this->getAuthenticatedAccess()),
        );
    }
}
