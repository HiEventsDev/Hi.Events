<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Events;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Services\Application\Handlers\Event\DTO\GetEventsDTO;
use HiEvents\Services\Application\Handlers\Event\GetEventsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetEventsAction extends BaseAction
{
    public function __construct(
        private readonly GetEventsHandler $getEventsHandler,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::ORGANIZER);

        $events = $this->getEventsHandler->handle(
            GetEventsDTO::fromArray([
                'accountId' => $this->getAuthenticatedAccountId(),
                'queryParams' => $this->getPaginationQueryParams($request),
            ]),
        );

        return $this->filterableResourceResponse(
            resource: EventResource::class,
            data: $events,
            domainObject: EventDomainObject::class,
        );
    }
}
