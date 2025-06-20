<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Event\EventResourcePublic;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicOrganizerEventsDTO;
use HiEvents\Services\Application\Handlers\Event\GetPublicEventsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOrganizerEventsPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetPublicEventsHandler $handler,
    )
    {
    }

    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $events = $this->handler->handle(new GetPublicOrganizerEventsDTO(
            organizerId: $organizerId,
            queryParams: $this->getPaginationQueryParams($request),
            authenticatedAccountId: $this->isUserAuthenticated() ? $this->getAuthenticatedAccountId() : null
        ));

        return $this->resourceResponse(
            resource: EventResourcePublic::class,
            data: $events,
        );
    }
}
